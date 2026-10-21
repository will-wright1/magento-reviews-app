<?php
/**
 * Typed accessor for all admin configuration values under
 * reviewautomation_review_automation/*. Single source of truth for defaults,
 * scope resolution, and casts.
 */
declare(strict_types=1);

namespace ReviewAutomation\Reviews\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use ReviewAutomation\Reviews\Model\Config\Source\PrimaryPlatform;

class Config
{
    private const XML_PATH_PREFIX = 'reviewautomation_review_automation/';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    public function isEnabled(?int $storeId = null): bool
    {
        return (bool) $this->getFlag('general/enabled', $storeId);
    }

    public function getEmail1DelayDays(?int $storeId = null): int
    {
        return max(0, (int) $this->getValue('general/email1_delay_days', $storeId));
    }

    public function getEmail1ReminderDelayDays(?int $storeId = null): int
    {
        $value = (int) $this->getValue('general/email1_reminder_delay_days', $storeId);
        return $value > 0 ? $value : 3;
    }

    public function getEmail2DelayHours(?int $storeId = null): int
    {
        return max(0, (int) $this->getValue('general/email2_delay_hours', $storeId));
    }

    public function getProductReviewDelayDays(?int $storeId = null): int
    {
        $raw = $this->getValue('general/product_review_delay_days', $storeId);
        if ($raw === null || $raw === '') {
            return 14;
        }
        $value = (int) $raw;
        return $value >= 0 ? $value : 14;
    }

    public function getTokenExpiryDays(?int $storeId = null): int
    {
        $value = (int) $this->getValue('general/token_expiry_days', $storeId);
        return $value > 0 ? $value : 60;
    }

    public function getSenderName(?int $storeId = null): string
    {
        return (string) ($this->getValue('general/sender_name', $storeId)
            ?: $this->scopeConfig->getValue('general/store_information/name', ScopeInterface::SCOPE_STORE, $storeId)
            ?: $this->scopeConfig->getValue('trans_email/ident_general/name', ScopeInterface::SCOPE_STORE, $storeId));
    }

    public function getSenderEmail(?int $storeId = null): string
    {
        return (string) ($this->getValue('general/sender_email', $storeId) ?: $this->scopeConfig->getValue('trans_email/ident_general/email', ScopeInterface::SCOPE_STORE, $storeId));
    }

    public function getAdminNotificationEmail(?int $storeId = null): string
    {
        return (string) ($this->getValue('general/admin_notification_email', $storeId) ?: $this->getSenderEmail($storeId));
    }

    public function getSatisfactionEmailTemplate(?int $storeId = null): string
    {
        return (string) ($this->getValue('general/satisfaction_email_template', $storeId)
            ?: 'reviewautomation_review_satisfaction_check');
    }

    public function getSatisfactionReminderEmailTemplate(?int $storeId = null): string
    {
        return (string) ($this->getValue('general/satisfaction_reminder_email_template', $storeId)
            ?: 'reviewautomation_review_satisfaction_reminder');
    }

    public function getReviewRequestEmailTemplate(?int $storeId = null): string
    {
        return (string) ($this->getValue('general/review_request_email_template', $storeId)
            ?: 'reviewautomation_review_request');
    }

    public function getProductReviewRequestEmailTemplate(?int $storeId = null): string
    {
        return (string) ($this->getValue('general/product_review_request_email_template', $storeId)
            ?: 'reviewautomation_product_review_request');
    }

    public function getAdminNotificationEmailTemplate(?int $storeId = null): string
    {
        return (string) ($this->getValue('general/admin_notification_email_template', $storeId)
            ?: 'reviewautomation_review_admin_feedback_notification');
    }

    /**
     * Reply-To for all customer-facing review emails. Empty string disables
     * the header (replies then go to the sender address).
     */
    public function getReplyToEmail(?int $storeId = null): string
    {
        return trim((string) $this->getValue('general/reply_to_email', $storeId));
    }

    /**
     * Quiet-hours send window. When enabled, the SendEmails cron only
     * dispatches between start hour (inclusive) and end hour (exclusive),
     * evaluated in the store's local timezone.
     */
    public function isSendWindowEnabled(?int $storeId = null): bool
    {
        return (bool) $this->getFlag('general/send_window_enabled', $storeId);
    }

    public function getSendWindowStartHour(?int $storeId = null): int
    {
        $value = (int) $this->getValue('general/send_window_start_hour', $storeId);
        return ($value >= 0 && $value <= 23) ? $value : 8;
    }

    public function getSendWindowEndHour(?int $storeId = null): int
    {
        $value = (int) $this->getValue('general/send_window_end_hour', $storeId);
        return ($value >= 1 && $value <= 24) ? $value : 19;
    }

    public function isEmail2NudgeEnabled(?int $storeId = null): bool
    {
        return (bool) $this->getFlag('general/email2_nudge_enabled', $storeId);
    }

    public function getEmail2NudgeDelayDays(?int $storeId = null): int
    {
        $value = (int) $this->getValue('general/email2_nudge_delay_days', $storeId);
        return $value > 0 ? $value : 5;
    }

    public function getReviewRequestNudgeEmailTemplate(?int $storeId = null): string
    {
        return (string) ($this->getValue('general/review_request_nudge_email_template', $storeId)
            ?: 'reviewautomation_review_request_nudge');
    }

    /**
     * When enabled and both platforms are configured, customers with a
     * Google-hosted mailbox (gmail.com / googlemail.com) get the Google CTA
     * and everyone else gets Trustpilot — lower friction than a blind split.
     */
    public function isGmailTargetingEnabled(?int $storeId = null): bool
    {
        return (bool) $this->getFlag('platforms/gmail_targeting_enabled', $storeId);
    }

    public function getGoogleReviewUrl(?int $storeId = null): string
    {
        return $this->httpsUrl((string) $this->getValue('platforms/google_url', $storeId));
    }

    public function getTrustpilotReviewUrl(?int $storeId = null): string
    {
        return $this->httpsUrl((string) $this->getValue('platforms/trustpilot_url', $storeId));
    }

    public function getPrimaryPlatform(?int $storeId = null): string
    {
        $value = (string) $this->getValue('platforms/primary_platform', $storeId);
        return in_array($value, [PrimaryPlatform::GOOGLE, PrimaryPlatform::TRUSTPILOT, PrimaryPlatform::BOTH], true)
            ? $value
            : PrimaryPlatform::BOTH;
    }

    public function shouldShowGoogle(?int $storeId = null): bool
    {
        $platform = $this->getPrimaryPlatform($storeId);
        return ($platform === PrimaryPlatform::GOOGLE || $platform === PrimaryPlatform::BOTH)
            && $this->getGoogleReviewUrl($storeId) !== '';
    }

    public function shouldShowTrustpilot(?int $storeId = null): bool
    {
        $platform = $this->getPrimaryPlatform($storeId);
        return ($platform === PrimaryPlatform::TRUSTPILOT || $platform === PrimaryPlatform::BOTH)
            && $this->getTrustpilotReviewUrl($storeId) !== '';
    }

    public function areMagentoProductReviewsEnabled(?int $storeId = null): bool
    {
        return (bool) $this->scopeConfig->isSetFlag(
            'catalog/review/active',
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function shouldSuppressRefunded(?int $storeId = null): bool
    {
        return (bool) $this->getFlag('suppression/suppress_refunded', $storeId);
    }

    public function shouldSuppressCancelled(?int $storeId = null): bool
    {
        return (bool) $this->getFlag('suppression/suppress_cancelled', $storeId);
    }

    public function getMinOrderValue(?int $storeId = null): float
    {
        return (float) $this->getValue('suppression/min_order_value', $storeId);
    }

    public function getMaxEmailsPerMonth(?int $storeId = null): int
    {
        $value = (int) $this->getValue('suppression/max_emails_per_month', $storeId);
        return $value > 0 ? $value : 1;
    }

    /**
     * Returns the set of store IDs allowed to generate review emails.
     * Empty array means "block all" — callers must treat empty as fail-safe
     * and skip processing (never interpret empty as "allow everything").
     *
     * @return int[]
     */
    public function getAllowedStoreIds(?int $storeId = null): array
    {
        $raw = $this->getValue('filters/allowed_store_ids', $storeId);
        if ($raw === null || $raw === '') {
            return [];
        }
        $ids = array_filter(
            array_map('intval', explode(',', (string) $raw)),
            static fn(int $id): bool => $id > 0
        );
        return array_values(array_unique($ids));
    }

    /**
     * Returns the maximum order age in days for review eligibility.
     * Orders older than this window are excluded from queueing and
     * suppressed at dispatch time. Defaults to 60 when unset, invalid,
     * or out of range (1-365).
     */
    public function getMaxOrderAgeDays(?int $storeId = null): int
    {
        $days = (int) $this->getValue('filters/max_order_age_days', $storeId);
        return $days > 0 && $days <= 365 ? $days : 60;
    }

    public function getBrandName(?int $storeId = null): string
    {
        return trim((string) $this->getValue('branding/name', $storeId)) ?: $this->getSenderName($storeId);
    }

    public function getAccentColor(?int $storeId = null): string
    {
        $color = (string) $this->getValue('branding/accent_color', $storeId);
        return preg_match('/^#[0-9a-fA-F]{6}$/D', $color) ? $color : '#2563eb';
    }

    public function getLogoUrl(?int $storeId = null): string
    {
        return $this->httpsUrl((string) $this->getValue('branding/logo_url', $storeId));
    }

    private function httpsUrl(string $url): string
    {
        $url = trim($url);
        return filter_var($url, FILTER_VALIDATE_URL) && parse_url($url, PHP_URL_SCHEME) === 'https'
            && !parse_url($url, PHP_URL_USER) && !parse_url($url, PHP_URL_PASS) ? $url : '';
    }

    private function getValue(string $path, ?int $storeId = null): mixed
    {
        return $this->scopeConfig->getValue(
            self::XML_PATH_PREFIX . $path,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    private function getFlag(string $path, ?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_PREFIX . $path,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }
}
