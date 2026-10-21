<?php
declare(strict_types=1);

namespace ReviewAutomation\Reviews\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class BoosterBatch extends AbstractDb
{
    protected function _construct()
    {
        $this->_init('reviewautomation_review_booster_batch', 'batch_id');
    }
}
