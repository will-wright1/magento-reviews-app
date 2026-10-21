<?php
declare(strict_types=1);

namespace ReviewAutomation\Reviews\Model\Email;

use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;
use ReviewAutomation\Reviews\Model\Config;
use ReviewAutomation\Reviews\Model\Queue;
use ReviewAutomation\Reviews\Model\ReviewPlatformResolver;

class ReviewRequestEmail
{
    public function __construct(
        private readonly MessageBuilder $messageBuilder,
        private readonly TemplateLoader $templateLoader,
        private readonly Config $config,
        private readonly ReviewPlatformResolver $platformResolver,
        private readonly StoreManagerInterface $storeManager,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Returns false on any failure so the cron caller moves on rather
     * than aborting the whole batch via an uncaught exception.
     *
     * @param bool $isNudge When true, renders the second-chance nudge
     *                      template instead of the standard Email 2.
     */
    public function send(Queue $queue, bool $isNudge = false): bool
    {
        $storeId = (int) $queue->getStoreId();
        $firstname = (string) ($queue->getCustomerFirstname() ?: 'there');
        $email = (string) $queue->getCustomerEmail();
        $token = (string) $queue->getToken();

        try {
            $store = $this->storeManager->getStore($storeId);
            $baseUrl = rtrim($store->getBaseUrl(), '/') . '/';
            $unsubscribeUrl = $baseUrl . 'reviewautomation_reviews/unsubscribe/index/token/' . $token;

            $platformButtonsHtml = $this->buildPlatformButtons($queue, $storeId);

            $templateId = $isNudge
                ? $this->config->getReviewRequestNudgeEmailTemplate($storeId)
                : $this->config->getReviewRequestEmailTemplate($storeId);
            $result = $this->templateLoader->render($templateId, [
                '__FIRSTNAME__'             => $this->esc($firstname),
                '__PLATFORM_BUTTONS_HTML__' => $platformButtonsHtml,
                '__UNSUBSCRIBE_URL__'       => $this->esc($unsubscribeUrl),
            ], $storeId);
        } catch (\Throwable $e) {
            $this->logger->error(sprintf(
                'ReviewAutomation: ReviewRequestEmail send failed for queue_id=%d, store_id=%d — %s',
                (int) $queue->getId(),
                $storeId,
                $e->getMessage()
            ));
            return false;
        }

        return $this->messageBuilder->send(
            $result['subject'],
            $result['html'],
            $this->config->getSenderEmail($storeId),
            $this->config->getSenderName($storeId),
            $email,
            $firstname,
            $storeId
        );
    }

    /**
     * Pre-render a single Google or Trustpilot CTA based on admin config.
     *
     * When both platforms are enabled, queue parity creates a stable 50/50
     * assignment so review requests are spread evenly without changing if a
     * send is retried. If only one platform is available, use that one.
     *
     * The button links to the tracked /response/go redirect (rather than the
     * platform directly) so the dashboard's "Platform Clicks" stat captures
     * email CTA clicks as well as thank-you page clicks.
     */
    private function buildPlatformButtons(Queue $queue, int $storeId): string
    {
        $platform = $this->platformResolver->resolveForQueue($queue, $storeId);
        if ($platform !== null) {
            $store = $this->storeManager->getStore($storeId);
            $trackedUrl = rtrim($store->getBaseUrl(), '/') . '/'
                . 'reviewautomation_reviews/response/go/token/' . rawurlencode((string) $queue->getToken())
                . '/platform/' . rawurlencode($platform);

            return $this->renderPlatformButton(
                $trackedUrl,
                $this->platformResolver->getButtonLabel($platform)
            );
        }

        return <<<HTML
<tr>
    <td align="center" style="padding:16px 0; font-family:Arial,Helvetica,sans-serif; font-size:14px; color:#888888;">
        (Review links will appear here once configured.)
    </td>
</tr>
HTML;
    }

    private function renderPlatformButton(string $url, string $label): string
    {
        $urlH = $this->esc($url);
        $labelH = $this->esc($label);

        return <<<HTML
<tr>
    <td align="center" style="padding:8px 0;">
        <a href="{$urlH}" target="_blank" rel="noopener" style="display:inline-block; background-color:__ACCENT_COLOR__; color:#ffffff; font-family:Arial,Helvetica,sans-serif; font-size:17px; font-weight:700; text-decoration:none; padding:16px 32px; border-radius:6px; min-width:260px; text-align:center;">
            {$labelH}
        </a>
    </td>
</tr>
HTML;
    }

    private function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
