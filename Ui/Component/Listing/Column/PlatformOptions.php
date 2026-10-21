<?php
/**
 * Filter options for the "Platform Clicked" column on the Review Queue grid.
 */
declare(strict_types=1);

namespace ReviewAutomation\Reviews\Ui\Component\Listing\Column;

use Magento\Framework\Data\OptionSourceInterface;
use ReviewAutomation\Reviews\Model\ReviewPlatformResolver;

class PlatformOptions implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => ReviewPlatformResolver::PLATFORM_GOOGLE, 'label' => __('Google')],
            ['value' => ReviewPlatformResolver::PLATFORM_TRUSTPILOT, 'label' => __('Trustpilot')],
        ];
    }
}
