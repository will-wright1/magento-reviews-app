<?php
/**
 * Review Booster batch — an admin-defined backfill of historical orders
 * drip-fed into the review queue at a capped daily rate.
 */
declare(strict_types=1);

namespace ReviewAutomation\Reviews\Model;

use Magento\Framework\Model\AbstractModel;

class BoosterBatch extends AbstractModel
{
    public const STATUS_ACTIVE    = 'active';
    public const STATUS_PAUSED    = 'paused';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    protected function _construct()
    {
        $this->_init(\ReviewAutomation\Reviews\Model\ResourceModel\BoosterBatch::class);
    }
}
