<?php
/**
 * Chooses which public review platform a queue row should receive.
 */
declare(strict_types=1);

namespace ReviewAutomation\Reviews\Model;

class ReviewPlatformResolver
{
    public const PLATFORM_GOOGLE = 'google';
    public const PLATFORM_TRUSTPILOT = 'trustpilot';

    public function __construct(
        private readonly Config $config
    ) {
    }

    public function resolveForQueue(Queue $queue, int $storeId): ?string
    {
        $showGoogle = $this->config->shouldShowGoogle($storeId);
        $showTrustpilot = $this->config->shouldShowTrustpilot($storeId);

        if ($showGoogle && $showTrustpilot) {
            // Friction-based targeting: a Google review requires a Google
            // account, so send the Google CTA to customers who provably have
            // one (Google-hosted mailbox) and Trustpilot (no account barrier)
            // to everyone else. Falls back to the stable 50/50 parity split
            // when targeting is disabled.
            if ($this->config->isGmailTargetingEnabled($storeId)) {
                return $this->hasGoogleMailbox((string) $queue->getCustomerEmail())
                    ? self::PLATFORM_GOOGLE
                    : self::PLATFORM_TRUSTPILOT;
            }

            return $this->getQueueSplitBucket($queue) === 0
                ? self::PLATFORM_GOOGLE
                : self::PLATFORM_TRUSTPILOT;
        }

        if ($showGoogle) {
            return self::PLATFORM_GOOGLE;
        }

        if ($showTrustpilot) {
            return self::PLATFORM_TRUSTPILOT;
        }

        return null;
    }

    public function getReviewUrl(string $platform, int $storeId): string
    {
        return $platform === self::PLATFORM_GOOGLE
            ? $this->config->getGoogleReviewUrl($storeId)
            : $this->config->getTrustpilotReviewUrl($storeId);
    }

    public function getButtonLabel(string $platform): string
    {
        return $platform === self::PLATFORM_GOOGLE
            ? 'Review us on Google'
            : 'Review us on Trustpilot';
    }

    private function hasGoogleMailbox(string $email): bool
    {
        $atPos = strrpos($email, '@');
        if ($atPos === false) {
            return false;
        }

        $domain = strtolower(trim(substr($email, $atPos + 1)));

        return in_array($domain, ['gmail.com', 'googlemail.com'], true);
    }

    private function getQueueSplitBucket(Queue $queue): int
    {
        $queueId = (int) $queue->getId();
        if ($queueId > 0) {
            return $queueId % 2;
        }

        return (int) (crc32((string) $queue->getToken()) % 2);
    }
}
