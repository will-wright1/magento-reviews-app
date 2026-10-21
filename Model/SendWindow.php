<?php
/**
 * Quiet-hours gate for the SendEmails cron.
 *
 * Review emails sent overnight land at the bottom of the morning inbox
 * pile; restricting dispatch to waking hours measurably improves open
 * rates. The window is evaluated in the store's configured timezone
 * (general/locale/timezone), start hour inclusive, end hour exclusive —
 * defaults 08:00-19:00.
 *
 * The cron keeps running outside the window; rows simply wait for the next
 * in-window tick. All batch queries are already idempotent/ordered, so
 * delayed rows are picked up first thing the next morning.
 */
declare(strict_types=1);

namespace ReviewAutomation\Reviews\Model;

use Magento\Framework\Stdlib\DateTime\TimezoneInterface;

class SendWindow
{
    public function __construct(
        private readonly Config $config,
        private readonly TimezoneInterface $timezone
    ) {
    }

    public function isOpen(?int $storeId = null): bool
    {
        if (!$this->config->isSendWindowEnabled($storeId)) {
            return true;
        }

        $start = $this->config->getSendWindowStartHour($storeId);
        $end = $this->config->getSendWindowEndHour($storeId);
        if ($start >= $end) {
            // Invalid window: do not send until the configuration is corrected.
            return false;
        }

        $localHour = (int) $this->timezone->scopeDate($storeId, null, true)->format('G');

        return $localHour >= $start && $localHour < $end;
    }
}
