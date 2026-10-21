<?php
/**
 * Per-row Edit / Delete actions for the suppression listing grid.
 */
declare(strict_types=1);

namespace ReviewAutomation\Reviews\Ui\Component\Listing\Column;

use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

class UnsubscribeActions extends Column
{
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        private readonly UrlInterface $urlBuilder,
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    public function prepareDataSource(array $dataSource)
    {
        if (!isset($dataSource['data']['items'])) {
            return $dataSource;
        }
        $name = $this->getData('name');
        foreach ($dataSource['data']['items'] as &$item) {
            $id = (int) ($item['unsubscribe_id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $item[$name] = [
                'edit' => [
                    'href'  => $this->urlBuilder->getUrl(
                        'reviewautomation_reviewautomation/unsubscribe/edit',
                        ['id' => $id]
                    ),
                    'label' => (string) __('Edit'),
                ],
                'delete' => [
                    'href' => $this->urlBuilder->getUrl(
                        'reviewautomation_reviewautomation/unsubscribe/delete',
                        ['id' => $id]
                    ),
                    'label'   => (string) __('Delete'),
                    'confirm' => [
                        'title'   => (string) __('Delete suppression entry?'),
                        'message' => (string) __(
                            'This customer email will be eligible to receive review request emails for FUTURE orders. Past queue rows already marked as unsubscribed are not affected.'
                        ),
                    ],
                ],
            ];
        }
        return $dataSource;
    }
}
