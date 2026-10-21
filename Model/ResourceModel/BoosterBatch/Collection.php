<?php
declare(strict_types=1);

namespace ReviewAutomation\Reviews\Model\ResourceModel\BoosterBatch;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    protected function _construct()
    {
        $this->_init(
            \ReviewAutomation\Reviews\Model\BoosterBatch::class,
            \ReviewAutomation\Reviews\Model\ResourceModel\BoosterBatch::class
        );
    }
}
