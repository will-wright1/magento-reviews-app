<?php
/**
 * Block for the "Thank You" landing page (happy path).
 *
 * Exposes the store-level Google / Trustpilot URLs and visibility flags so
 * the template can render the correct CTA buttons. Falls back to the
 * default store if no token is provided.
 */
declare(strict_types=1);

namespace ReviewAutomation\Reviews\Block;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use ReviewAutomation\Reviews\Model\Config;
use ReviewAutomation\Reviews\Model\Queue;
use ReviewAutomation\Reviews\Model\ReviewPlatformResolver;
use ReviewAutomation\Reviews\Model\TokenLookup;

class Thankyou extends Template
{
    private ?int $resolvedStoreId = null;
    private ?Queue $resolvedQueue = null;
    private bool $hasResolvedQueue = false;
    private ?string $resolvedPlatform = null;
    private bool $hasResolvedPlatform = false;

    public function __construct(
        Context $context,
        private readonly Config $config,
        private readonly ReviewPlatformResolver $platformResolver,
        private readonly TokenLookup $tokenLookup,
        private readonly RequestInterface $request,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Resolve the store ID from the token (if present) or fall back to
     * the current store.
     */
    private function getResolvedStoreId(): int
    {
        if ($this->resolvedStoreId === null) {
            $queue = $this->getResolvedQueue();
            $this->resolvedStoreId = $queue
                ? (int) $queue->getStoreId()
                : (int) $this->_storeManager->getStore()->getId();
        }
        return $this->resolvedStoreId;
    }

    public function shouldShowGoogle(): bool
    {
        $platform = $this->getResolvedPlatform();
        if ($platform !== null) {
            return $platform === ReviewPlatformResolver::PLATFORM_GOOGLE;
        }

        return $this->config->shouldShowGoogle($this->getResolvedStoreId());
    }

    public function shouldShowTrustpilot(): bool
    {
        $platform = $this->getResolvedPlatform();
        if ($platform !== null) {
            return $platform === ReviewPlatformResolver::PLATFORM_TRUSTPILOT;
        }

        return $this->config->shouldShowTrustpilot($this->getResolvedStoreId());
    }

    public function getGoogleReviewUrl(): string
    {
        return $this->config->getGoogleReviewUrl($this->getResolvedStoreId());
    }

    public function getTrustpilotReviewUrl(): string
    {
        return $this->config->getTrustpilotReviewUrl($this->getResolvedStoreId());
    }

    /**
     * CTA href for the Google button — tracked redirect when we have a
     * valid token, direct platform URL otherwise.
     */
    public function getGoogleCtaUrl(): string
    {
        return $this->getCtaUrl(ReviewPlatformResolver::PLATFORM_GOOGLE, $this->getGoogleReviewUrl());
    }

    /**
     * CTA href for the Trustpilot button — tracked redirect when we have a
     * valid token, direct platform URL otherwise.
     */
    public function getTrustpilotCtaUrl(): string
    {
        return $this->getCtaUrl(ReviewPlatformResolver::PLATFORM_TRUSTPILOT, $this->getTrustpilotReviewUrl());
    }

    private function getCtaUrl(string $platform, string $directUrl): string
    {
        $queue = $this->getResolvedQueue();
        if ($queue === null) {
            return $directUrl;
        }

        return $this->getUrl('reviewautomation_reviews/response/go', [
            'token' => (string) $queue->getToken(),
            'platform' => $platform,
        ]);
    }

    private function getResolvedQueue(): ?Queue
    {
        if (!$this->hasResolvedQueue) {
            $token = (string) $this->request->getParam('token');
            $this->resolvedQueue = $token !== ''
                ? $this->tokenLookup->loadByToken($token)
                : null;
            $this->hasResolvedQueue = true;
        }

        return $this->resolvedQueue;
    }

    private function getResolvedPlatform(): ?string
    {
        if (!$this->hasResolvedPlatform) {
            $queue = $this->getResolvedQueue();
            $this->resolvedPlatform = $queue
                ? $this->platformResolver->resolveForQueue($queue, (int) $queue->getStoreId())
                : null;
            $this->hasResolvedPlatform = true;
        }

        return $this->resolvedPlatform;
    }
}
