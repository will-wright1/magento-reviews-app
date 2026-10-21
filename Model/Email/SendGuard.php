<?php
declare(strict_types=1);
namespace ReviewAutomation\Reviews\Model\Email;
use Magento\Framework\App\ResourceConnection;
use Magento\Sales\Model\Order;
use ReviewAutomation\Reviews\Model\Config;
use ReviewAutomation\Reviews\Model\OrderAnchorResolver;
use ReviewAutomation\Reviews\Model\Queue;
use ReviewAutomation\Reviews\Model\SendWindow;
use ReviewAutomation\Reviews\Model\SuppressionService;
/** Rechecked inside the send lock for cron and manual dispatch alike. */
class SendGuard
{
    public function __construct(
        private readonly Config $config,
        private readonly SuppressionService $suppression,
        private readonly ResourceConnection $resource,
        private readonly OrderAnchorResolver $anchors,
        private readonly SendWindow $window
    ) {}
    public function reason(Queue $queue, string $type): ?string
    {
        $store = (int) $queue->getStoreId();
        if (!$this->config->isEnabled() || !$this->config->isEnabled($store)) { return 'Module disabled.'; }
        if (!in_array($store, $this->config->getAllowedStoreIds(), true)) { return 'Store not allowed.'; }
        if (!$this->window->isOpen($store)) { return 'Outside the store sending window.'; }
        if (in_array($queue->getStatus(), [Queue::STATUS_UNSUBSCRIBED, Queue::STATUS_SUPPRESSED], true)
            || $this->suppression->isSuppressed((string) $queue->getCustomerEmail())) { return 'Customer suppressed.'; }
        if (!filter_var($queue->getCustomerEmail(), FILTER_VALIDATE_EMAIL)
            || !filter_var($this->config->getSenderEmail($store), FILTER_VALIDATE_EMAIL)) { return 'Invalid recipient or sender.'; }
        $created = strtotime((string) $queue->getCreatedAt() . ' UTC');
        if (!$created || time() > $created + $this->config->getTokenExpiryDays($store) * 86400) { return 'Queue token expired.'; }
        $db = $this->resource->getConnection();
        $order = $db->fetchRow($db->select()->from($this->resource->getTableName('sales_order'))
            ->where('entity_id = ?', (int) $queue->getOrderId()));
        if (!$order || (int) $order['store_id'] !== $store || $order['state'] !== Order::STATE_COMPLETE) {
            return 'Order is missing or no longer complete.';
        }
        if ((float) $order['grand_total'] < $this->config->getMinOrderValue($store)) { return 'Below minimum order value.'; }
        if ($this->config->shouldSuppressRefunded($store)) {
            $refunded = $db->fetchOne($db->select()->from($this->resource->getTableName('sales_creditmemo'), ['COUNT(*)'])
                ->where('order_id = ?', (int) $queue->getOrderId()));
            if ((int) $refunded > 0) { return 'Order refunded.'; }
        }
        $anchor = $this->anchors->resolveAnchorForOrderId((int) $queue->getOrderId());
        if ($anchor === null) { return 'Order fulfilment timestamp unavailable.'; }
        if (!(int) $queue->getData('age_exempt')
            && $anchor->getTimestamp() < time() - $this->config->getMaxOrderAgeDays($store) * 86400) {
            return 'Order outside the age window.';
        }
        if ($type === SendResult::TYPE_EMAIL1) {
            if (!(int) $queue->getData('age_exempt')
                && $anchor->getTimestamp() > time() - $this->config->getEmail1DelayDays($store) * 86400) {
                return 'Order is too recent.';
            }
            $count = $db->fetchOne($db->select()->from($this->resource->getTableName('reviewautomation_review_queue'), ['COUNT(*)'])
                ->where('customer_email = ?', (string) $queue->getCustomerEmail())
                ->where('email1_sent_at >= ?', gmdate('Y-m-01 00:00:00')));
            if ((int) $count >= $this->config->getMaxEmailsPerMonth($store)) { return 'Monthly campaign limit reached.'; }
        }
        if (in_array($type, [SendResult::TYPE_EMAIL2, SendResult::TYPE_NUDGE], true)
            && !$this->config->shouldShowGoogle($store) && !$this->config->shouldShowTrustpilot($store)) {
            return 'No public review platform configured.';
        }
        return null;
    }
}
