<?php
/**
 * Helper for finding the latest order that satisfies the same eligibility
 * rules PopulateQueue uses (state=complete, store_id allowed, anchor inside
 * the max_order_age_days window).
 *
 * Used by the "Send Test Email" config button to pick a real order's data
 * for a realistic test render.
 *
 * NOTE on duplication: PopulateQueue contains the canonical version of this
 * eligibility query, including credit-memo skipping, customer-email checks,
 * and various per-store overrides. This class deliberately implements a
 * smaller, simpler version — just "newest sendable-looking order" — because
 * extracting PopulateQueue's full filter into a shared service would be a
 * larger refactor with regression risk on the cron path. TODO: consolidate
 * into a single OrderEligibilityCriteria class once we're confident the
 * cron-side regression surface is well-tested.
 */
declare(strict_types=1);

namespace ReviewAutomation\Reviews\Model;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Sql\Expression as SqlExpression;
use Magento\Sales\Model\Order;

class OrderEligibilityQuery
{
    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly Config $config
    ) {
    }

    /**
     * Find the most recently anchored order that would currently be eligible
     * for review-email queueing. Returns the raw row array (entity_id,
     * increment_id, customer_email, customer_firstname, store_id) or null
     * if no order matches.
     *
     * @return array<string,mixed>|null
     */
    public function findLatestEligibleOrder(): ?array
    {
        $allowedStoreIds = $this->config->getAllowedStoreIds();
        if (empty($allowedStoreIds)) {
            return null;
        }

        $maxAgeDays = $this->config->getMaxOrderAgeDays();
        $ageLowerBound = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))
            ->modify("-{$maxAgeDays} days")
            ->format('Y-m-d H:i:s');

        $connection      = $this->resourceConnection->getConnection();
        $orderTable      = $this->resourceConnection->getTableName('sales_order');
        $shipmentTable   = $this->resourceConnection->getTableName('sales_shipment');

        $shipmentSubquery = $connection->select()
            ->from(
                $shipmentTable,
                ['order_id', 'latest_shipment_at' => new SqlExpression('MAX(created_at)')]
            )
            ->group('order_id');

        $completeStateLiteral = $connection->quote(Order::STATE_COMPLETE);
        $anchorSql = sprintf(
            'COALESCE(ship.latest_shipment_at, CASE WHEN o.state = %s THEN o.updated_at END)',
            $completeStateLiteral
        );

        $select = $connection->select()
            ->from(
                ['o' => $orderTable],
                [
                    'entity_id',
                    'increment_id',
                    'customer_email',
                    'customer_firstname',
                    'store_id',
                    'resolved_anchor' => new SqlExpression($anchorSql),
                ]
            )
            ->joinLeft(
                ['ship' => $shipmentSubquery],
                'ship.order_id = o.entity_id',
                []
            )
            ->where('o.state = ?', Order::STATE_COMPLETE)
            ->where('o.store_id IN (?)', $allowedStoreIds)
            ->where('o.customer_email IS NOT NULL')
            ->where('o.customer_email != ?', '')
            ->where($anchorSql . ' >= ?', $ageLowerBound)
            ->order(new SqlExpression($anchorSql . ' DESC'))   // newest first
            ->limit(1);

        $row = $connection->fetchRow($select);
        return $row ?: null;
    }
}
