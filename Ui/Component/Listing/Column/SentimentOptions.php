<?php
/**
 * Options source for the Sentiment filter/column.
 */
declare(strict_types=1);

namespace ReviewAutomation\Reviews\Ui\Component\Listing\Column;

use Magento\Framework\Data\OptionSourceInterface;
use ReviewAutomation\Reviews\Model\Queue;

class SentimentOptions implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => Queue::SENTIMENT_HAPPY,   'label' => __('Happy')],
            ['value' => Queue::SENTIMENT_UNHAPPY,  'label' => __('Unhappy')],
        ];
    }
}
