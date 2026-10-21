<?php
/**
 * Options source for the queue Status filter/column.
 */
declare(strict_types=1);

namespace ReviewAutomation\Reviews\Ui\Component\Listing\Column;

use Magento\Framework\Data\OptionSourceInterface;
use ReviewAutomation\Reviews\Model\Queue;

class StatusOptions implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => Queue::STATUS_PENDING,              'label' => __('Pending')],
            ['value' => Queue::STATUS_EMAIL1_SENT,          'label' => __('Email 1 Sent')],
            ['value' => Queue::STATUS_EMAIL1_REMINDER_SENT, 'label' => __('Email 1 Reminder Sent')],
            ['value' => Queue::STATUS_CLICKED_HAPPY,        'label' => __('Clicked Happy')],
            ['value' => Queue::STATUS_CLICKED_UNHAPPY,      'label' => __('Clicked Unhappy')],
            ['value' => Queue::STATUS_EMAIL2_SENT,          'label' => __('Email 2 Sent')],
            ['value' => Queue::STATUS_PRODUCT_REVIEW_SENT,  'label' => __('Product Review Sent')],
            ['value' => Queue::STATUS_PRODUCT_REVIEW_SKIPPED, 'label' => __('Product Review Skipped')],
            ['value' => Queue::STATUS_FEEDBACK_RECEIVED,    'label' => __('Feedback Received')],
            ['value' => Queue::STATUS_UNSUBSCRIBED,         'label' => __('Unsubscribed')],
            ['value' => Queue::STATUS_SUPPRESSED,           'label' => __('Suppressed')],
        ];
    }
}
