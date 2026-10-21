<?php
declare(strict_types=1);
namespace ReviewAutomation\Reviews\Observer;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
class PrivateResponse implements ObserverInterface
{
    public function __construct(private readonly RequestInterface $request, private readonly ResponseInterface $response) {}
    public function execute(Observer $observer): void
    {
        if ($this->request->getModuleName() !== 'reviewautomation_reviews') { return; }
        $this->response->setHeader('Cache-Control', 'private, no-store, max-age=0', true);
        $this->response->setHeader('Referrer-Policy', 'no-referrer', true);
        $this->response->setHeader('X-Robots-Tag', 'noindex, nofollow', true);
    }
}
