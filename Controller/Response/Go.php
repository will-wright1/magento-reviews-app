<?php

declare(strict_types=1);

namespace ReviewAutomation\Reviews\Controller\Response;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;
use ReviewAutomation\Reviews\Model\Queue;
use ReviewAutomation\Reviews\Model\ResourceModel\Queue as QueueResource;
use ReviewAutomation\Reviews\Model\ReviewPlatformResolver;
use ReviewAutomation\Reviews\Model\TokenLookup;

class Go implements HttpGetActionInterface
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly RedirectFactory $redirectFactory,
        private readonly TokenLookup $tokenLookup,
        private readonly QueueResource $queueResource,
        private readonly ReviewPlatformResolver $platformResolver,
        private readonly StoreManagerInterface $storeManager,
        private readonly DateTime $dateTime,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(): ResultInterface
    {
        $redirect = $this->redirectFactory->create();
        $token = (string) $this->request->getParam('token');
        $platform = (string) $this->request->getParam('platform');

        if (!in_array($platform, [ReviewPlatformResolver::PLATFORM_GOOGLE, ReviewPlatformResolver::PLATFORM_TRUSTPILOT], true)) {
            return $redirect->setPath('reviewautomation_reviews/response/expired');
        }

        $queue = $token !== '' ? $this->tokenLookup->loadByToken($token) : null;

        $storeId = $queue !== null
            ? (int) $queue->getStoreId()
            : (int) $this->storeManager->getStore()->getId();

        $url = $this->platformResolver->getReviewUrl($platform, $storeId);
        if (trim($url) === '') {
            // Platform not configured for this store — nothing sensible to
            // redirect to, so fall back to the expired/info page.
            return $redirect->setPath('reviewautomation_reviews/response/expired');
        }

        if ($queue !== null) {
            $this->recordClick($queue, $platform);
        }

        return $redirect->setUrl($url);
    }


    private function recordClick(Queue $queue, string $platform): void
    {
        if ((string) $queue->getData('platform_click_at') !== '') {
            return;
        }

        try {
            $queue->setData('platform_clicked', $platform);
            $queue->setData('platform_click_at', $this->dateTime->gmtDate());
            $this->queueResource->save($queue);
        } catch (\Throwable $e) {
            // Tracking must never block the customer reaching the platform.
            $this->logger->error('ReviewAutomation: Go click save error: ' . $e->getMessage());
        }
    }


}
