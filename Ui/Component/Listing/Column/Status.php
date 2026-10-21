<?php
/**
 * Colour-coded status badge for the queue grid.
 *
 * Renders the status value inside a small inline label with a background
 * colour that makes it scannable at a glance:
 *
 *   pending              -> grey
 *   email1_sent          -> blue
 *   email1_reminder_sent -> teal
 *   clicked_happy        -> green
 *   clicked_unhappy      -> red
 *   email2_sent          -> purple
 *   product_review_sent  -> deep orange
 *   product_review_skipped -> amber
 *   feedback_received    -> orange
 *   unsubscribed         -> dark grey
 *   suppressed           -> light grey
 */
declare(strict_types=1);

namespace ReviewAutomation\Reviews\Ui\Component\Listing\Column;

use Magento\Ui\Component\Listing\Columns\Column;

class Status extends Column
{
    private const COLOUR_MAP = [
        'pending'              => ['bg' => '#e0e0e0', 'fg' => '#333333'],
        'email1_sent'          => ['bg' => '#2196F3', 'fg' => '#ffffff'],
        'email1_reminder_sent' => ['bg' => '#009688', 'fg' => '#ffffff'],
        'clicked_happy'        => ['bg' => '#4CAF50', 'fg' => '#ffffff'],
        'clicked_unhappy'      => ['bg' => '#f44336', 'fg' => '#ffffff'],
        'email2_sent'          => ['bg' => '#9C27B0', 'fg' => '#ffffff'],
        'product_review_sent'  => ['bg' => '#E65100', 'fg' => '#ffffff'],
        'product_review_skipped' => ['bg' => '#FFC107', 'fg' => '#333333'],
        'feedback_received'    => ['bg' => '#F58220', 'fg' => '#ffffff'],
        'unsubscribed'         => ['bg' => '#616161', 'fg' => '#ffffff'],
        'suppressed'           => ['bg' => '#bdbdbd', 'fg' => '#333333'],
    ];

    private const LABEL_MAP = [
        'pending'              => 'Pending',
        'email1_sent'          => 'Email 1 Sent',
        'email1_reminder_sent' => 'Email 1 Reminder Sent',
        'clicked_happy'        => 'Clicked Happy',
        'clicked_unhappy'      => 'Clicked Unhappy',
        'email2_sent'          => 'Email 2 Sent',
        'product_review_sent'  => 'Product Review Sent',
        'product_review_skipped' => 'Product Review Skipped',
        'feedback_received'    => 'Feedback Received',
        'unsubscribed'         => 'Unsubscribed',
        'suppressed'           => 'Suppressed',
    ];

    public function prepareDataSource(array $dataSource): array
    {
        if (!isset($dataSource['data']['items'])) {
            return $dataSource;
        }

        $fieldName = $this->getData('name');

        foreach ($dataSource['data']['items'] as &$item) {
            if (!isset($item[$fieldName])) {
                continue;
            }
            $status = (string) $item[$fieldName];
            $colours = self::COLOUR_MAP[$status] ?? ['bg' => '#e0e0e0', 'fg' => '#333333'];
            $label = self::LABEL_MAP[$status] ?? ucfirst(str_replace('_', ' ', $status));

            $item[$fieldName . '_html'] = sprintf(
                '<span style="display:inline-block;padding:4px 10px;border-radius:3px;font-size:12px;font-weight:600;background:%s;color:%s;">%s</span>',
                $colours['bg'],
                $colours['fg'],
                htmlspecialchars($label)
            );
            // Keep the original value for filtering/sorting.
            $item[$fieldName . '_orig'] = $status;
        }

        return $dataSource;
    }
}
