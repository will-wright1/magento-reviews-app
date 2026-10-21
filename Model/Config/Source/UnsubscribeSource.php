<?php
/**
 * Option source for the suppression `source` column. Used by the listing
 * grid filter and by any read-only display of the source value.
 */
declare(strict_types=1);

namespace ReviewAutomation\Reviews\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use ReviewAutomation\Reviews\Model\SuppressionService;

class UnsubscribeSource implements OptionSourceInterface
{
    /**
     * @return array<int, array{value: string, label: string}>
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => SuppressionService::SOURCE_SELF,             'label' => __('Customer Self-Unsubscribe')],
            ['value' => SuppressionService::SOURCE_ADMIN_ORDER_GRID, 'label' => __('Admin: Order Grid Mass Action')],
            ['value' => SuppressionService::SOURCE_ADMIN_ORDER_VIEW, 'label' => __('Admin: Order View Page')],
            ['value' => SuppressionService::SOURCE_ADMIN_MANUAL,     'label' => __('Admin: Manual Grid Entry')],
            ['value' => SuppressionService::SOURCE_LEGACY_IMPORT,    'label' => __('Legacy Import')],
        ];
    }
}
