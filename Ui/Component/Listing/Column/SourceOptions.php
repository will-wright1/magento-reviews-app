<?php
/**
 * Filter options for the "Source" column on the Review Queue grid.
 */
declare(strict_types=1);

namespace ReviewAutomation\Reviews\Ui\Component\Listing\Column;

use Magento\Framework\Data\OptionSourceInterface;

class SourceOptions implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => 'organic', 'label' => __('Organic')],
            ['value' => 'booster', 'label' => __('Booster')],
        ];
    }
}
