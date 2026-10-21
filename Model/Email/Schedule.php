<?php
declare(strict_types=1);
namespace ReviewAutomation\Reviews\Model\Email;
use ReviewAutomation\Reviews\Model\Config;
use ReviewAutomation\Reviews\Model\Queue;
/** Determines due messages from timestamps; satisfaction never determines eligibility. */
class Schedule
{
    public function __construct(private readonly Config $config) {}
    public function next(Queue $queue, int $now): ?string
    {
        if (in_array((string) $queue->getStatus(), [Queue::STATUS_UNSUBSCRIBED, Queue::STATUS_SUPPRESSED], true)) {
            return null;
        }
        $store = (int) $queue->getStoreId();
        $first = $this->timestamp($queue, 'email1_sent_at');
        $second = $this->timestamp($queue, 'email2_sent_at');
        if ($first === null) {
            $created = $this->timestamp($queue, 'created_at');
            return $created !== null && $created + 300 <= $now ? SendResult::TYPE_EMAIL1 : null;
        }
        if ($second === null) {
            if ($first + $this->config->getEmail2DelayHours($store) * 3600 <= $now) {
                return SendResult::TYPE_EMAIL2;
            }
            if (!$queue->getSentiment() && $this->timestamp($queue, 'email1_reminder_sent_at') === null
                && $first + $this->config->getEmail1ReminderDelayDays($store) * 86400 <= $now) {
                return SendResult::TYPE_EMAIL1_REMINDER;
            }
            return null;
        }
        if ($this->config->isEmail2NudgeEnabled($store)
            && $this->timestamp($queue, 'email2_nudge_sent_at') === null
            && $this->timestamp($queue, 'platform_click_at') === null
            && $second + $this->config->getEmail2NudgeDelayDays($store) * 86400 <= $now) {
            return SendResult::TYPE_NUDGE;
        }
        if ($this->config->areMagentoProductReviewsEnabled($store)
            && $this->timestamp($queue, 'product_review_sent_at') === null
            && $second + $this->config->getProductReviewDelayDays($store) * 86400 <= $now) {
            return SendResult::TYPE_PRODUCT_REVIEW;
        }
        return null;
    }
    private function timestamp(Queue $queue, string $field): ?int
    {
        $value = (string) $queue->getData($field);
        if ($value === '') { return null; }
        $parsed = strtotime($value . ' UTC');
        return $parsed === false ? null : $parsed;
    }
}
