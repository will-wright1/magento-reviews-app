<?php
declare(strict_types=1);

namespace ReviewAutomation\Reviews\Model\ResourceModel\Feedback;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use ReviewAutomation\Reviews\Model\Feedback;
use ReviewAutomation\Reviews\Model\ResourceModel\Feedback as FeedbackResource;

class Collection extends AbstractCollection
{
    /**
     * @var string
     */
    protected $_idFieldName = 'feedback_id';

    /**
     * @var string
     */
    protected $_eventPrefix = 'reviewautomation_review_feedback_collection';

    /**
     * @var string
     */
    protected $_eventObject = 'feedback_collection';

    protected function _construct(): void
    {
        $this->_init(Feedback::class, FeedbackResource::class);
    }
}
