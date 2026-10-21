<?php
declare(strict_types=1);

namespace ReviewAutomation\Reviews\Block\Adminhtml\Unsubscribe\Edit;

use Magento\Backend\Block\Widget\Context;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;

class DeleteButton implements ButtonProviderInterface
{
    public function __construct(
        private readonly Context $context,
        private readonly RequestInterface $request
    ) {
    }

    public function getButtonData(): array
    {
        $id = (int) $this->request->getParam('id');
        if ($id <= 0) {
            return [];
        }
        $url = $this->context->getUrlBuilder()->getUrl('*/*/delete', ['id' => $id]);
        $confirmMsg = __('Delete this suppression entry? Future orders for this email will be eligible for review emails again.');
        return [
            'label'      => __('Delete'),
            'class'      => 'delete',
            'on_click'   => sprintf("deleteConfirm('%s', '%s')", $confirmMsg, $url),
            'sort_order' => 20,
        ];
    }
}
