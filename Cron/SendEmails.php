<?php
declare(strict_types=1);
namespace ReviewAutomation\Reviews\Cron;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Lock\LockManagerInterface;
use Psr\Log\LoggerInterface;
use ReviewAutomation\Reviews\Model\Config;
use ReviewAutomation\Reviews\Model\Email\Schedule;
use ReviewAutomation\Reviews\Model\Email\SendDispatcher;
use ReviewAutomation\Reviews\Model\Queue;
use ReviewAutomation\Reviews\Model\QueueFactory;
use ReviewAutomation\Reviews\Model\ResourceModel\Queue as QueueResource;
class SendEmails
{
    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly QueueFactory $queues,
        private readonly QueueResource $queueResource,
        private readonly Config $config,
        private readonly Schedule $schedule,
        private readonly SendDispatcher $dispatcher,
        private readonly LockManagerInterface $locks,
        private readonly LoggerInterface $logger
    ) {}
    public function execute(): void
    {
        if (!$this->config->isEnabled() || !$this->config->getAllowedStoreIds()
            || !$this->locks->lock('reviewautomation_review_cron', 0)) { return; }
        try {
            $db = $this->resource->getConnection();
            $allowedStores = $this->config->getAllowedStoreIds();
            $maxTokenAge = max(array_map(fn(int $store): int => $this->config->getTokenExpiryDays($store), $allowedStores));
            $oldestToken = gmdate('Y-m-d H:i:s', time() - $maxTokenAge * 86400);
            $cursor = 0;
            $sent = 0;
            // Keyset pagination scans past future-due rows without starving later eligible rows.
            do {
                $ids = $db->fetchCol($db->select()->from($this->resource->getTableName('reviewautomation_review_queue'), ['queue_id'])
                    ->where('queue_id > ?', $cursor)
                    ->where('created_at >= ?', $oldestToken)
                    ->where('store_id IN (?)', $this->config->getAllowedStoreIds())
                    ->where('status NOT IN (?)', [Queue::STATUS_UNSUBSCRIBED, Queue::STATUS_SUPPRESSED])
                    ->order('queue_id ASC')->limit(200));
                foreach ($ids as $id) {
                    $cursor = (int) $id;
                    try {
                        $queue = $this->queues->create();
                        $this->queueResource->load($queue, $cursor);
                        if (!$queue->getId()) { continue; }
                        $type = $this->schedule->next($queue, time());
                        if ($type === null) { continue; }
                        $result = $this->dispatcher->dispatch($queue, true, $type);
                        if ($result->isSuccess()) { $sent++; }
                        elseif ($result->getMessageType() !== null) {
                            $this->logger->warning('ReviewAutomation: Queue ' . $cursor . ' send failed: ' . $result->getErrorMessage());
                        }
                    } catch (\Throwable $e) {
                        $this->logger->error('ReviewAutomation: Queue ' . $cursor . ': ' . $e->getMessage());
                    }
                    if ($sent >= 50) { break 2; }
                }
            } while (count($ids) === 200);
            $this->logger->info('ReviewAutomation: Sent ' . $sent . ' messages.');
        } catch (\Throwable $e) {
            $this->logger->error('ReviewAutomation: Cron failed: ' . $e->getMessage());
        } finally { $this->locks->unlock('reviewautomation_review_cron'); }
    }
}
