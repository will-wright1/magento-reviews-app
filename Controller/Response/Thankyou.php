<?php

declare(strict_types=1);

namespace ReviewAutomation\Reviews\Controller\Response;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\View\Result\PageFactory;
use ReviewAutomation\Reviews\Model\Queue;
use ReviewAutomation\Reviews\Model\TokenLookup;

class Thankyou implements HttpGetActionInterface
{
    public function __construct(
        private readonly PageFactory $pageFactory,
        private readonly RequestInterface $request,
        private readonly RedirectFactory $redirectFactory,
        private readonly TokenLookup $tokenLookup
    ) {
    }

    public function execute(): ResultInterface
    {
        $page = $this->pageFactory->create();
        $page->getConfig()->getTitle()->set(__('Thank you'));
        return $page;
    }


}
