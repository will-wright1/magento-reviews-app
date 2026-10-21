<?php
/**
 * Cron: populate the review queue from recently-completed orders.
 *
 * Runs every 30 minutes. Finds orders in state=complete older than the
 * configured email1 delay and inserts a queue row for each one, skipping:
 *
 *   - orders already queued
 *   - customers on the unsubscribe list
 *   - refunded orders (if suppress_refunded is on)
 *   - cancelled orders (belt-and-braces — state filter already excludes these)
 *   - orders below the configured minimum grand_total
 *   - customers who have already received max_emails_per_month this calendar month
 *
 * Kept deliberately SQL-driven: we're iterating a potentially large window of
 * orders and building Order objects for each would be gratuitous.
 */
declare(strict_types=1);

namespace ReviewAutomation\Reviews\Cron;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Sql\Expression as SqlExpression;
use Magento\Framework\Lock\LockManagerInterface;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Sales\Model\Order;
use Psr\Log\LoggerInterface;
use ReviewAutomation\Reviews\Model\Config;
use ReviewAutomation\Reviews\Model\Queue;

class PopulateQueue
{
    private const LOCK_NAME = 'reviewautomation_review_populate_queue';
    private const LOCK_TIMEOUT = 600;
    private const CANDIDATE_LIMIT = 500;

    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly Config $config,
        private readonly LockManagerInterface $lockManager,
        private readonly DateTime $dateTime,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(): void
    {
        if (!$this->config->isEnabled()) {
            return;
        }

        if (!$this->lockManager->lock(self::LOCK_NAME, self::LOCK_TIMEOUT)) {
            $this->logger->info('ReviewAutomation: PopulateQueue already running, skipping');
            return;
        }

        try {
            $this->populate();
        } catch (\Throwable $e) {
            $this->logger->error('ReviewAutomation: PopulateQueue error: ' . $e->getMessage());
        } finally {
            $this->lockManager->unlock(self::LOCK_NAME);
        }
    }

    private function populate(): void
    {
        foreach ($this->config->getAllowedStoreIds() as $storeId) {
            if ($this->config->isEnabled($storeId)) { $this->populateStore($storeId); }
        }
    }

    private function populateStore(int $storeId): void
    {
        $connection      = $this->resourceConnection->getConnection();
        $orderTable      = $this->resourceConnection->getTableName('sales_order');
        $queueTable      = $this->resourceConnection->getTableName('reviewautomation_review_queue');
        $unsubTable      = $this->resourceConnection->getTableName('reviewautomation_review_unsubscribe');
        $creditmemoTable = $this->resourceConnection->getTableName('sales_creditmemo');
        $shipmentTable   = $this->resourceConnection->getTableName('sales_shipment');

        // --- Order Filters (Part 1.5) ---

        // Fail-safe: an empty allowlist blocks the whole run. We do not assume
        // "empty = allow everything" because that's the dangerous default.
        $allowedStoreIds = [$storeId];
        if (empty($allowedStoreIds)) {
            $this->logger->info(
                'ReviewAutomation: PopulateQueue skipped — no allowed_store_ids configured. ' .
                'Set at least one allowed store view in Stores → Configuration → Review Automation → Review Automation → Order Filters.'
            );
            return;
        }

        $maxAgeDays = $this->config->getMaxOrderAgeDays($storeId);
        $delayDays  = $this->config->getEmail1DelayDays($storeId);

        // Sanity: the upper bound (must be at least email1_delay_days old) and the lower
        // bound (must not be older than max_order_age_days) must form a non-empty window.
        if ($maxAgeDays <= $delayDays) {
            $this->logger->warning(sprintf(
                'ReviewAutomation: PopulateQueue skipped — max_order_age_days (%d) must be greater than email1_delay_days (%d). ' .
                'Window is empty, no orders can be queued.',
                $maxAgeDays,
                $delayDays
            ));
            return;
        }

        // Compute window bounds in explicit UTC. gmtDate() returns Magento's
        // canonical UTC string which is unambiguous regardless of the server's
        // PHP timezone.
        $cutoff        = $this->dateTime->gmtDate('Y-m-d H:i:s', strtotime("-{$delayDays} days"));
        $ageLowerBound = $this->dateTime->gmtDate('Y-m-d H:i:s', strtotime("-{$maxAgeDays} days"));

        // Anchor expression — see Model\OrderAnchorResolver for the design
        // rationale. Latest shipment.created_at wins; falls back to
        // order.updated_at only when state=complete (covers virtual /
        // downloadable / manually completed orders with no shipment).
        $completeStateLiteral = $connection->quote(Order::STATE_COMPLETE);
        $anchorSql = sprintf(
            'COALESCE(ship.latest_shipment_at, CASE WHEN o.state = %s THEN o.updated_at END)',
            $completeStateLiteral
        );

        // Subquery: latest shipment per order_id. We can't simply join the
        // shipment table directly because partial-shipment orders would
        // multiply the row count; the GROUP BY guarantees one row per order.
        $shipmentSubquery = $connection->select()
            ->from(
                $shipmentTable,
                ['order_id', 'latest_shipment_at' => new SqlExpression('MAX(created_at)')]
            )
            ->group('order_id');

        // Candidate orders: complete, anchor inside the email1_delay/max_age
        // window, allowed store, not already queued. We pre-filter unsubscribes,
        // credit memos and queue presence in SQL so the PHP loop only applies
        // per-store config (min_order_value, rate limit, refunded toggle).
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
            ->where($anchorSql . ' >= ?', $ageLowerBound)       // anchor-based lower bound
            ->where($anchorSql . ' <= ?', $cutoff)              // anchor-based upper bound (email1_delay buffer)
            ->where(
                'NOT EXISTS (SELECT 1 FROM ' . $queueTable . ' q WHERE q.order_id = o.entity_id)'
            )
            ->where(
                'NOT EXISTS (SELECT 1 FROM ' . $unsubTable . ' u WHERE u.customer_email = o.customer_email)'
            )
            ->order(new SqlExpression($anchorSql . ' ASC'))
            ->limit(self::CANDIDATE_LIMIT);

        $select->where('o.grand_total >= ?', $this->config->getMinOrderValue($storeId));
        if ($this->config->shouldSuppressRefunded($storeId)) {
            $select->where('NOT EXISTS (SELECT 1 FROM ' . $creditmemoTable . ' cm WHERE cm.order_id = o.entity_id)');
        }
        $select->where('(SELECT COUNT(*) FROM ' . $queueTable . ' rate_q WHERE rate_q.customer_email = o.customer_email AND rate_q.email1_sent_at >= '
            . $connection->quote($this->dateTime->gmtDate('Y-m-01 00:00:00')) . ') < ?', $this->config->getMaxEmailsPerMonth($storeId));
        $candidates = $connection->fetchAll($select);
        if (empty($candidates)) {
            return;
        }

        $inserted = 0;
        $skipped = 0;

        foreach ($candidates as $row) {
            $storeId = (int) $row['store_id'];

            if (!$this->config->isEnabled($storeId)) {
                $skipped++;
                continue;
            }

            // Minimum order value (per-store).
            $minValue = $this->config->getMinOrderValue($storeId);
            if ($minValue > 0 && (float) $row['grand_total'] < $minValue) {
                $skipped++;
                continue;
            }

            // Suppress refunded orders.
            if ($this->config->shouldSuppressRefunded($storeId)) {
                $cm = $connection->fetchOne(
                    $connection->select()
                        ->from($creditmemoTable, ['COUNT(*)'])
                        ->where('order_id = ?', (int) $row['entity_id'])
                );
                if ((int) $cm > 0) {
                    $skipped++;
                    continue;
                }
            }

            // Suppress cancelled (belt-and-braces — we already filtered state=complete).
            if ($this->config->shouldSuppressCancelled($storeId) && $row['status'] === 'canceled') {
                $skipped++;
                continue;
            }

            // Calendar-month rate limit per customer email.
            $maxPerMonth = $this->config->getMaxEmailsPerMonth($storeId);
            $monthStart = $this->dateTime->gmtDate('Y-m-01 00:00:00');
            $alreadySent = $connection->fetchOne(
                $connection->select()
                    ->from($queueTable, ['COUNT(*)'])
                    ->where('customer_email = ?', (string) $row['customer_email'])
                    ->where('email1_sent_at >= ?', $monthStart)
            );
            if ((int) $alreadySent >= $maxPerMonth) {
                $skipped++;
                continue;
            }

            try {
                $connection->insert($queueTable, [
                    'order_id'           => (int) $row['entity_id'],
                    'order_increment_id' => (string) $row['increment_id'],
                    'customer_email'     => (string) $row['customer_email'],
                    'customer_firstname' => (string) ($row['customer_firstname'] ?? ''),
                    'store_id'           => $storeId,
                    'token'              => bin2hex(random_bytes(32)),
                    'status'             => Queue::STATUS_PENDING,
                    'created_at'         => $this->dateTime->gmtDate(),
                    'updated_at'         => $this->dateTime->gmtDate(),
                ]);
                $inserted++;
            } catch (\Throwable $e) {
                $this->logger->warning(sprintf(
                    'ReviewAutomation: Failed to queue order %s — %s',
                    $row['increment_id'],
                    $e->getMessage()
                ));
            }
        }

        if ($inserted > 0 || $skipped > 0) {
            $this->logger->info(sprintf(
                'ReviewAutomation: PopulateQueue — Inserted: %d, Skipped: %d (of %d candidates)',
                $inserted,
                $skipped,
                count($candidates)
            ));
        }
    }
}
