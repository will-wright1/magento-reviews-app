<?php
declare(strict_types=1);

namespace ReviewAutomation\Reviews\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class Unsubscribe extends AbstractDb
{
    protected function _construct(): void
    {
        $this->_init('reviewautomation_review_unsubscribe', 'unsubscribe_id');
    }
}
