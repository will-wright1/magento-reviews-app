<?php
/**
 * Multiselect source model for the "Allowed Store Views" admin config field.
 *
 * Lists every non-admin store view. The admin store (id=0) is filtered out
 * defensively even though Magento's getStoreValuesForForm(false, …) helper
 * is supposed to exclude it already.
 */
declare(strict_types=1);

namespace ReviewAutomation\Reviews\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Magento\Store\Model\System\Store as SystemStore;

class AllowedStores implements OptionSourceInterface
{
    public function __construct(
        private readonly SystemStore $systemStore
    ) {
    }

    /**
     * @return array<int, array{value: int|string, label: string}>
     */
    public function toOptionArray(): array
    {
        $options = [];
        foreach ($this->systemStore->getStoreValuesForForm(false, true) as $option) {
            if (isset($option['value']) && (int) $option['value'] === 0) {
                continue;
            }
            $options[] = $option;
        }
        return $options;
    }
}
