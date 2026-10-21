<?php
/**
 * Review Booster admin page — new-batch form plus existing batch list.
 */
declare(strict_types=1);

namespace ReviewAutomation\Reviews\Block\Adminhtml\Booster;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\App\ResourceConnection;
use ReviewAutomation\Reviews\Model\ResourceModel\BoosterBatch\Collection;
use ReviewAutomation\Reviews\Model\ResourceModel\BoosterBatch\CollectionFactory;

class Manage extends Template
{
    private ?array $sentCounts = null;

    public function __construct(
        Context $context,
        private readonly CollectionFactory $batchCollectionFactory,
        private readonly ResourceConnection $resourceConnection,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getBatches(): Collection
    {
        return $this->batchCollectionFactory->create()->setOrder('batch_id', 'DESC');
    }

    /**
     * Email 1 sent counts per batch, one query.
     *
     * @return array<int, int> batch_id => sent count
     */
    public function getSentCount(int $batchId): int
    {
        if ($this->sentCounts === null) {
            $connection = $this->resourceConnection->getConnection();
            $queueTable = $this->resourceConnection->getTableName('reviewautomation_review_queue');
            $this->sentCounts = [];
            $rows = $connection->fetchPairs(
                $connection->select()
                    ->from($queueTable, ['booster_batch_id', 'cnt' => new \Zend_Db_Expr('COUNT(*)')])
                    ->where('booster_batch_id IS NOT NULL')
                    ->where('email1_sent_at IS NOT NULL')
                    ->group('booster_batch_id')
            );
            foreach ($rows as $id => $cnt) {
                $this->sentCounts[(int) $id] = (int) $cnt;
            }
        }

        return $this->sentCounts[$batchId] ?? 0;
    }

    public function getSaveUrl(): string
    {
        return $this->getUrl('reviewautomation_reviewautomation/booster/save');
    }

    public function getPreviewUrl(): string
    {
        return $this->getUrl('reviewautomation_reviewautomation/booster/preview');
    }

    public function getChangeStatusUrl(): string
    {
        return $this->getUrl('reviewautomation_reviewautomation/booster/changestatus');
    }
}
