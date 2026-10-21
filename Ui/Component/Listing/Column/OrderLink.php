<?php
/**
 * Renders the order_increment_id as a clickable link to the sales order
 * admin view. Used in both the queue and feedback grids.
 */
declare(strict_types=1);

namespace ReviewAutomation\Reviews\Ui\Component\Listing\Column;

use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

class OrderLink extends Column
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

        $fieldName = $this->getData('name');

        foreach ($dataSource['data']['items'] as &$item) {
            if (!isset($item[$fieldName]) || !isset($item['order_id'])) {
                continue;
            }
            $orderId = (int) $item['order_id'];
            $incrementId = htmlspecialchars((string) $item[$fieldName], ENT_QUOTES, 'UTF-8');
            $url = $this->urlBuilder->getUrl('sales/order/view', ['order_id' => $orderId]);

            $item[$fieldName . '_html'] = sprintf(
                '<a href="%s" title="View order">%s</a>',
                htmlspecialchars($url, ENT_QUOTES, 'UTF-8'),
                $incrementId
            );
            $item[$fieldName . '_orig'] = $item[$fieldName];
        }

        return $dataSource;
    }
}
