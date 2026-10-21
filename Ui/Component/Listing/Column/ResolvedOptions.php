<?php
declare(strict_types=1);

namespace ReviewAutomation\Reviews\Ui\Component\Listing\Column;

use Magento\Framework\Data\OptionSourceInterface;

class ResolvedOptions implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => '1', 'label' => __('Yes')],
            ['value' => '0', 'label' => __('No')],
        ];
    }
}
