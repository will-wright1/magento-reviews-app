<?php
/**
 * Truncates long text (e.g. feedback_text) in the grid to a scannable
 * length with a "show more / show less" toggle. No JS dependency — uses
 * a pure-CSS detail/summary element styled to look like an inline toggle.
 */
declare(strict_types=1);

namespace ReviewAutomation\Reviews\Ui\Component\Listing\Column;

use Magento\Ui\Component\Listing\Columns\Column;

class TruncatedText extends Column
{
    private const MAX_LENGTH = 120;

    public function prepareDataSource(array $dataSource): array
    {
        if (!isset($dataSource['data']['items'])) {
            return $dataSource;
        }

        $fieldName = $this->getData('name');

        foreach ($dataSource['data']['items'] as &$item) {
            $text = (string) ($item[$fieldName] ?? '');
            $escaped = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

            if (mb_strlen($text) <= self::MAX_LENGTH) {
                $item[$fieldName . '_html'] = '<span style="font-size:13px;line-height:1.5;">' . nl2br($escaped) . '</span>';
            } else {
                $short = htmlspecialchars(mb_substr($text, 0, self::MAX_LENGTH), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '&hellip;';
                $item[$fieldName . '_html'] = sprintf(
                    '<details style="font-size:13px;line-height:1.5;">'
                    . '<summary style="cursor:pointer;list-style:none;">%s <span style="color:#F58220;font-size:12px;white-space:nowrap;">[show more]</span></summary>'
                    . '<div style="margin-top:6px;">%s</div>'
                    . '</details>',
                    $short,
                    nl2br($escaped)
                );
            }
        }

        return $dataSource;
    }
}
