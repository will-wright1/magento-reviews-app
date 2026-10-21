<?php
/**
 * Actions column for the feedback grid — "View" link that opens the detail/edit page.
 */
declare(strict_types=1);

namespace ReviewAutomation\Reviews\Ui\Component\Listing\Column;

use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

class FeedbackActions extends Column
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

    public function prepareDataSource(array $dataSource): array
    {
        if (!isset($dataSource['data']['items'])) {
            return $dataSource;
        }

        foreach ($dataSource['data']['items'] as &$item) {
            $item[$this->getData('name')] = [
                'view' => [
                    'href'  => $this->urlBuilder->getUrl(
                        'reviewautomation_reviewautomation/feedback/edit',
                        ['id' => $item['feedback_id']]
                    ),
                    'label' => __('View'),
                ],
            ];
        }

        return $dataSource;
    }
}
