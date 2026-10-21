<?php
/**
 * Generic "link expired" landing page for invalid or expired tokens.
 *
 * Deliberately vague — we don't leak whether a token existed, expired, or
 * was never valid. Just a branded dead-end with a way back to the homepage.
 */
declare(strict_types=1);

namespace ReviewAutomation\Reviews\Controller\Response;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\View\Result\PageFactory;

class Expired implements HttpGetActionInterface
{
    public function __construct(
        private readonly PageFactory $pageFactory
    ) {
    }

    public function execute(): ResultInterface
    {
        $page = $this->pageFactory->create();
        $page->getConfig()->getTitle()->set(__('Link expired'));
        return $page;
    }
}
