<?php
declare(strict_types=1);

namespace ReviewAutomation\Reviews\Model;

use Magento\Framework\Model\AbstractModel;
use ReviewAutomation\Reviews\Model\ResourceModel\Unsubscribe as UnsubscribeResource;

class Unsubscribe extends AbstractModel
{
    /**
     * @var string
     */
    protected $_eventPrefix = 'reviewautomation_review_unsubscribe';

    protected function _construct(): void
    {
        $this->_init(UnsubscribeResource::class);
    }
}
