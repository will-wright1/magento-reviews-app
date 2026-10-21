<?php
declare(strict_types=1);

namespace ReviewAutomation\Reviews\Model;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Api\ShipmentRepositoryInterface;
use Magento\Sales\Model\Order;
use Psr\Log\LoggerInterface;

class OrderAnchorResolver
{
    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly ShipmentRepositoryInterface $shipmentRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Resolve the anchor timestamp for the given order ID.
     *
     * Returns null if the order doesn't exist, or exists but has no shipments
     * and is not in STATE_COMPLETE. Callers must distinguish "retry later"
     * (no anchor yet) from "give up" (queue row aged out) themselves; this
     * helper has no opinion on either.
     */
    public function resolveAnchorForOrderId(int $orderId): ?\DateTimeImmutable
    {
        try {
            $order = $this->orderRepository->get($orderId);
        } catch (NoSuchEntityException $e) {
            // Order vanished between queueing and dispatch — extremely rare.
            return null;
        }

        return $this->resolveAnchorForOrder($order);
    }

    /**
     * Same as resolveAnchorForOrderId but for callers that already hold an
     * Order instance and want to avoid the redundant repository load.
     */
    public function resolveAnchorForOrder(OrderInterface $order): ?\DateTimeImmutable
    {
        $utc = new \DateTimeZone('UTC');

        // Priority 1: latest shipment created_at.
        $latestShipment = $this->getLatestShipmentTimestamp((int) $order->getEntityId(), $utc);
        if ($latestShipment !== null) {
            return $latestShipment;
        }

        // Priority 2: order.updated_at, but only when state=complete.
        if ((string) $order->getState() === Order::STATE_COMPLETE) {
            $updatedAt = (string) $order->getUpdatedAt();
            if ($updatedAt !== '') {
                try {
                    return new \DateTimeImmutable($updatedAt, $utc);
                } catch (\Exception $e) {
                    $this->logger->warning(sprintf(
                        'ReviewAutomation/OrderAnchorResolver: invalid updated_at "%s" on order %d: %s',
                        $updatedAt,
                        (int) $order->getEntityId(),
                        $e->getMessage()
                    ));
                    return null;
                }
            }
        }

        // No shipment, not state=complete — caller should retry later, not suppress.
        return null;
    }

    /**
     * Find the most recent shipment for an order and return its created_at
     * in UTC. Returns null if no shipments exist.
     */
    private function getLatestShipmentTimestamp(int $orderId, \DateTimeZone $utc): ?\DateTimeImmutable
    {
        $criteria = $this->searchCriteriaBuilder
            ->addFilter('order_id', $orderId)
            ->create();

        $shipments = $this->shipmentRepository->getList($criteria)->getItems();
        if (empty($shipments)) {
            return null;
        }

        $latest = null;
        foreach ($shipments as $shipment) {
            $createdAt = (string) $shipment->getCreatedAt();
            if ($createdAt === '') {
                continue;
            }
            try {
                $dt = new \DateTimeImmutable($createdAt, $utc);
            } catch (\Exception $e) {
                $this->logger->warning(sprintf(
                    'ReviewAutomation/OrderAnchorResolver: invalid shipment created_at "%s" on order %d: %s',
                    $createdAt,
                    $orderId,
                    $e->getMessage()
                ));
                continue;
            }
            if ($latest === null || $dt > $latest) {
                $latest = $dt;
            }
        }

        return $latest;
    }
}
