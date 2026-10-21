<?php
declare(strict_types=1);

namespace ReviewAutomation\Reviews\Model;

use Magento\Framework\Model\AbstractModel;
use ReviewAutomation\Reviews\Model\ResourceModel\Feedback as FeedbackResource;

class Feedback extends AbstractModel
{
    /**
     * @var string
     */
    protected $_eventPrefix = 'reviewautomation_review_feedback';

    protected function _construct(): void
    {
        $this->_init(FeedbackResource::class);
    }
}
