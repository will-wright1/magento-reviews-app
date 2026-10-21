<?php
declare(strict_types=1);

namespace ReviewAutomation\Reviews\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class PrimaryPlatform implements OptionSourceInterface
{
    public const GOOGLE = 'google';
    public const TRUSTPILOT = 'trustpilot';
    public const BOTH = 'both';

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => self::GOOGLE, 'label' => __('Google only')],
            ['value' => self::TRUSTPILOT, 'label' => __('Trustpilot only')],
            ['value' => self::BOTH, 'label' => __('Both (50/50 split)')],
        ];
    }
}
