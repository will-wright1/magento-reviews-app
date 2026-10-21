<?php
/**
 * Review Booster drip feed.
 *
 * Runs hourly. For every active booster batch, tops the queue up to the
 * batch's per-day cap (counted per UTC calendar day), inserting rows with
 * source=booster and age_exempt=1 so the normal SendEmails pipeline
 * processes them despite their age. All organic per-order suppressions
 * (store enabled, min order value, refunded, cancelled, monthly rate
 * limit) are applied here exactly as in Cron\PopulateQueue.
 *
 * A batch completes automatically when its date window yields no further
 * candidates.
 */
declare(strict_types=1);

namespace ReviewAutomation\Reviews\Cron;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Lock\LockManagerInterface;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Psr\Log\LoggerInterface;
use ReviewAutomation\Reviews\Model\BoosterBatch;
use ReviewAutomation\Reviews\Model\BoosterEligibility;
use ReviewAutomation\Reviews\Model\Config;
use ReviewAutomation\Reviews\Model\Queue;
use ReviewAutomation\Reviews\Model\ResourceModel\BoosterBatch as BoosterBatchResource;
use ReviewAutomation\Reviews\Model\ResourceModel\BoosterBatch\CollectionFactory as BatchCollectionFactory;

class FeedBoosterBatches
{
    private const LOCK_NAME = 'reviewautomation_review_booster_feed';
    private const LOCK_TIMEOUT = 5;

    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly BatchCollectionFactory $batchCollectionFactory,
        private readonly BoosterBatchResource $batchResource,
        private readonly BoosterEligibility $eligibility,
        private readonly Config $config,
        private readonly LockManagerInterface $lockManager,
        private readonly DateTime $dateTime,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(): void
    {
        if (!$this->config->isEnabled() || !$this->config->getAllowedStoreIds()) {
            return;
        }

        if (!$this->lockManager->lock(self::LOCK_NAME, self::LOCK_TIMEOUT)) {
            return;
        }

        try {
            $batches = $this->batchCollectionFactory->create()
                ->addFieldToFilter('status', BoosterBatch::STATUS_ACTIVE);

            /** @var BoosterBatch $batch */
            foreach ($batches as $batch) {
                $this->feedBatch($batch);
            }
        } catch (\Throwable $e) {
            $this->logger->error('ReviewAutomation: FeedBoosterBatches error: ' . $e->getMessage());
        } finally {
            $this->lockManager->unlock(self::LOCK_NAME);
        }
    }

    private function feedBatch(BoosterBatch $batch): void
    {
        $connection = $this->resourceConnection->getConnection();
        $queueTable = $this->resourceConnection->getTableName('reviewautomation_review_queue');
        $creditmemoTable = $this->resourceConnection->getTableName('sales_creditmemo');

        $batchId = (int) $batch->getId();
        $perDay = max(1, (int) $batch->getData('per_day'));

        // Rows already queued today (UTC) by this batch.
        $todayStart = $this->dateTime->gmtDate('Y-m-d 00:00:00');
        $queuedToday = (int) $connection->fetchOne(
            $connection->select()
                ->from($queueTable, ['COUNT(*)'])
                ->where('booster_batch_id = ?', $batchId)
                ->where('created_at >= ?', $todayStart)
        );

        $quota = $perDay - $queuedToday;
        if ($quota <= 0) {
            return;
        }

        // Over-fetch to survive PHP-level skips without an extra round trip.
        $candidates = $this->eligibility->fetchCandidates(
            (string) $batch->getData('date_from'),
            (string) $batch->getData('date_to'),
            (float) $batch->getData('min_order_value'),
            $quota * 3
        );

        if (empty($candidates)) {
            $batch->setData('status', BoosterBatch::STATUS_COMPLETED);
            $batch->setData('last_run_at', $this->dateTime->gmtDate());
            $this->batchResource->save($batch);
            $this->logger->info(sprintf(
                'ReviewAutomation: Booster batch %d completed — no remaining candidates (total queued: %d).',
                $batchId,
                (int) $batch->getData('total_queued')
            ));
            return;
        }

        $inserted = 0;
        $skipped = 0;

        foreach ($candidates as $row) {
            if ($inserted >= $quota) {
                break;
            }

            $storeId = (int) $row['store_id'];

            if (!$this->config->isEnabled($storeId)) {
                $skipped++;
                continue;
            }

            // Per-store minimum order value (batch min already applied in SQL).
            $minValue = $this->config->getMinOrderValue($storeId);
            if ($minValue > 0 && (float) $row['grand_total'] < $minValue) {
                $skipped++;
                continue;
            }

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
                    'source'             => 'booster',
                    'age_exempt'         => 1,
                    'booster_batch_id'   => $batchId,
                    'created_at'         => $this->dateTime->gmtDate(),
                    'updated_at'         => $this->dateTime->gmtDate(),
                ]);
                $inserted++;
            } catch (\Throwable $e) {
                $this->logger->warning(sprintf(
                    'ReviewAutomation: Booster batch %d failed to queue order %s — %s',
                    $batchId,
                    $row['increment_id'],
                    $e->getMessage()
                ));
            }
        }

        $batch->setData('total_queued', (int) $batch->getData('total_queued') + $inserted);
        $batch->setData('last_run_at', $this->dateTime->gmtDate());
        $this->batchResource->save($batch);

        if ($inserted > 0 || $skipped > 0) {
            $this->logger->info(sprintf(
                'ReviewAutomation: Booster batch %d — Queued: %d, Skipped: %d (quota %d).',
                $batchId,
                $inserted,
                $skipped,
                $quota
            ));
        }
    }
}
