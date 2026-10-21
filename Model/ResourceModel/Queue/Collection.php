<?php
declare(strict_types=1);

namespace ReviewAutomation\Reviews\Model\ResourceModel\Queue;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use ReviewAutomation\Reviews\Model\Queue;
use ReviewAutomation\Reviews\Model\ResourceModel\Queue as QueueResource;

class Collection extends AbstractCollection
{
    /**
     * @var string
     */
    protected $_idFieldName = 'queue_id';

    /**
     * @var string
     */
    protected $_eventPrefix = 'reviewautomation_review_queue_collection';

    /**
     * @var string
     */
    protected $_eventObject = 'queue_collection';

    protected function _construct(): void
    {
        $this->_init(Queue::class, QueueResource::class);
    }
}
