<?php
declare(strict_types=1);

namespace ReviewAutomation\Reviews\Controller\Adminhtml\Queue;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;

class Index extends Action
{
    public const ADMIN_RESOURCE = 'ReviewAutomation_Reviews::queue';

    public function __construct(
        Context $context,
        private readonly PageFactory $pageFactory
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $page = $this->pageFactory->create();
        $page->setActiveMenu('ReviewAutomation_Reviews::queue');
        $page->getConfig()->getTitle()->prepend(__('Review Queue'));
        return $page;
    }
}
