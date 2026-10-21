<?php
/**
 * Eligible-order query for Review Booster batches.
 *
 * Mirrors Cron\PopulateQueue's candidate SQL, but windows on the batch's
 * explicit date range (anchored on latest shipment, falling back to
 * order.updated_at for shipment-less complete orders) instead of
 * max_order_age_days. SQL-level exclusions: wrong state/store, already
 * queued (any batch, organic included), unsubscribed, missing email.
 *
 * Per-order config checks (min order value, refunded/cancelled suppression,
 * monthly rate limit) are applied by the feed cron in PHP, matching the
 * organic populate flow.
 */
declare(strict_types=1);

namespace ReviewAutomation\Reviews\Model;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Select;
use Magento\Framework\DB\Sql\Expression as SqlExpression;
use Magento\Sales\Model\Order;

class BoosterEligibility
{
    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly Config $config
    ) {
    }

    /**
     * SQL-level count of candidate orders for a prospective batch. This is
     * an upper bound: the drip cron still applies per-order config checks.
     */
    public function countCandidates(string $dateFrom, string $dateTo, float $minOrderValue): int
    {
        $select = $this->buildSelect($dateFrom, $dateTo, $minOrderValue);
        $select->reset(Select::COLUMNS)->columns(new SqlExpression('COUNT(*)'));

        return (int) $this->resourceConnection->getConnection()->fetchOne($select);
    }

    /**
     * Fetch up to $limit candidate order rows, oldest anchor first.
     *
     * @return array<int, array<string, mixed>>
     */
    public function fetchCandidates(string $dateFrom, string $dateTo, float $minOrderValue, int $limit): array
    {
        $select = $this->buildSelect($dateFrom, $dateTo, $minOrderValue)
            ->order(new SqlExpression($this->getAnchorSql() . ' ASC'))
            ->limit($limit);

        return $this->resourceConnection->getConnection()->fetchAll($select);
    }

    private function buildSelect(string $dateFrom, string $dateTo, float $minOrderValue): Select
    {
        $connection = $this->resourceConnection->getConnection();
        $orderTable = $this->resourceConnection->getTableName('sales_order');
        $queueTable = $this->resourceConnection->getTableName('reviewautomation_review_queue');
        $unsubTable = $this->resourceConnection->getTableName('reviewautomation_review_unsubscribe');
        $shipmentTable = $this->resourceConnection->getTableName('sales_shipment');

        $allowedStoreIds = $this->config->getAllowedStoreIds();
        if (empty($allowedStoreIds)) {
            // Same fail-safe as the organic populate: no allowed stores means
            // no candidates, ever.
            $allowedStoreIds = [-1];
        }

        $anchorSql = $this->getAnchorSql();

        $shipmentSubquery = $connection->select()
            ->from(
                $shipmentTable,
                ['order_id', 'latest_shipment_at' => new SqlExpression('MAX(created_at)')]
            )
            ->group('order_id');

        $select = $connection->select()
            ->from(
                ['o' => $orderTable],
                [
                    'entity_id',
                    'increment_id',
                    'customer_email',
                    'customer_firstname',
                    'store_id',
                    'grand_total',
                    'state',
                    'status',
                    'created_at',
                    'updated_at',
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
            ->where($anchorSql . ' >= ?', $dateFrom . ' 00:00:00')
            ->where($anchorSql . ' <= ?', $dateTo . ' 23:59:59')
            ->where(
                'NOT EXISTS (SELECT 1 FROM ' . $queueTable . ' q WHERE q.order_id = o.entity_id)'
            )
            ->where(
                'NOT EXISTS (SELECT 1 FROM ' . $queueTable
                . ' q2 WHERE q2.customer_email = o.customer_email AND q2.booster_batch_id IS NOT NULL)'
            )
            ->where(
                'NOT EXISTS (SELECT 1 FROM ' . $unsubTable . ' u WHERE u.customer_email = o.customer_email)'
            );

        if ($minOrderValue > 0) {
            $select->where('o.grand_total >= ?', $minOrderValue);
        }

        return $select;
    }

    private function getAnchorSql(): string
    {
        $connection = $this->resourceConnection->getConnection();
        $completeStateLiteral = $connection->quote(Order::STATE_COMPLETE);

        return sprintf(
            'COALESCE(ship.latest_shipment_at, CASE WHEN o.state = %s THEN o.updated_at END)',
            $completeStateLiteral
        );
    }
}
