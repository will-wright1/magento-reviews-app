<?php
declare(strict_types=1);
namespace ReviewAutomation\Reviews\Controller\Response;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\View\Result\PageFactory;
use ReviewAutomation\Reviews\Model\TokenLookup;
/** GET renders a confirmation; mail-scanner requests cannot record sentiment. */
class Click implements HttpGetActionInterface
{
    public function __construct(private readonly RequestInterface $request, private readonly TokenLookup $tokens,
        private readonly PageFactory $pages, private readonly RedirectFactory $redirects) {}
    public function execute(): ResultInterface
    {
        $sentiment = (string) $this->request->getParam('sentiment');
        $queue = $this->tokens->loadByToken((string) $this->request->getParam('token'));
        if (!$queue || !in_array($sentiment, ['happy', 'unhappy'], true)) {
            return $this->redirects->create()->setPath('reviewautomation_reviews/response/expired');
        }
        $page = $this->pages->create();
        $page->getConfig()->getTitle()->set(__('Share your feedback'));
        return $page;
    }
}
