<?php
declare(strict_types=1);
namespace ReviewAutomation\Reviews\Controller\Preview;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\View\Result\PageFactory;
use ReviewAutomation\Reviews\Model\TestEmailPreview;

/** Demonstrates the landing pages without accepting customer tokens or saving anything. */
class Index implements HttpGetActionInterface
{
    public function __construct(private readonly RequestInterface $request,
        private readonly PageFactory $pages, private readonly RedirectFactory $redirects) {}

    public function execute(): ResultInterface
    {
        if (!TestEmailPreview::isValidStep((string) $this->request->getParam('step', 'happy'))) {
            return $this->redirects->create()->setPath('reviewautomation_reviews/preview/index', ['step' => 'happy']);
        }
        $page = $this->pages->create();
        $page->getConfig()->getTitle()->set(__('Review email preview'));
        return $page;
    }
}
