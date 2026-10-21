<?php
declare(strict_types=1);

namespace ReviewAutomation\Reviews\Model\ResourceModel\Unsubscribe;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use ReviewAutomation\Reviews\Model\Unsubscribe;
use ReviewAutomation\Reviews\Model\ResourceModel\Unsubscribe as UnsubscribeResource;

class Collection extends AbstractCollection
{
    /**
     * @var string
     */
    protected $_idFieldName = 'unsubscribe_id';

    /**
     * @var string
     */
    protected $_eventPrefix = 'reviewautomation_review_unsubscribe_collection';

    /**
     * @var string
     */
    protected $_eventObject = 'unsubscribe_collection';

    protected function _construct(): void
    {
        $this->_init(Unsubscribe::class, UnsubscribeResource::class);
    }
}
