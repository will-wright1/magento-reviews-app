<?php
/**
 * Star-rating renderer for the feedback grid.
 *
 * Displays 1-5 filled/empty stars. Null or 0 shows "N/A".
 */
declare(strict_types=1);

namespace ReviewAutomation\Reviews\Ui\Component\Listing\Column;

use Magento\Ui\Component\Listing\Columns\Column;

class Rating extends Column
{
    public function prepareDataSource(array $dataSource): array
    {
        if (!isset($dataSource['data']['items'])) {
            return $dataSource;
        }

        $fieldName = $this->getData('name');

        foreach ($dataSource['data']['items'] as &$item) {
            $rating = isset($item[$fieldName]) ? (int) $item[$fieldName] : 0;
            if ($rating < 1 || $rating > 5) {
                $item[$fieldName . '_html'] = '<span style="color:#999;">N/A</span>';
            } else {
                $stars = str_repeat('&#9733;', $rating) . str_repeat('&#9734;', 5 - $rating);
                $item[$fieldName . '_html'] = sprintf(
                    '<span style="color:#F58220;font-size:16px;letter-spacing:2px;">%s</span>',
                    $stars
                );
            }
        }

        return $dataSource;
    }
}
