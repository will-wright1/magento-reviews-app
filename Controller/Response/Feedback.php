<?php
/**
 * Private feedback form shown after a customer clicks "Not Great" in Email 1.
 *
 * URL: /reviewautomation_reviews/response/feedback/token/{token}
 *
 * The critical UX promise of the unhappy path: your complaint goes straight
 * to the team, never to a public review platform. If the token is invalid
 * or expired we redirect to the homepage (silent, non-informative) — we
 * don't want to leak which tokens exist.
 */
declare(strict_types=1);

namespace ReviewAutomation\Reviews\Controller\Response;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\View\Result\PageFactory;
use ReviewAutomation\Reviews\Model\TokenLookup;

class Feedback implements HttpGetActionInterface
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly PageFactory $pageFactory,
        private readonly RedirectFactory $redirectFactory,
        private readonly TokenLookup $tokenLookup
    ) {
    }

    public function execute(): ResultInterface
    {
        $token = (string) $this->request->getParam('token');
        $queue = $this->tokenLookup->loadByToken($token);
        if ($queue === null) {
            return $this->redirectFactory->create()->setPath('reviewautomation_reviews/response/expired');
        }

        $page = $this->pageFactory->create();
        $page->getConfig()->getTitle()->set(__('Tell us what went wrong'));
        return $page;
    }
}
