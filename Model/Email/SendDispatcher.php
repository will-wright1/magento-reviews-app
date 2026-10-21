<?php
declare(strict_types=1);
namespace ReviewAutomation\Reviews\Model\Email;
use Magento\Framework\Lock\LockManagerInterface;
use Magento\Framework\Stdlib\DateTime\DateTime;
use ReviewAutomation\Reviews\Model\Queue;
use ReviewAutomation\Reviews\Model\ResourceModel\Queue as QueueResource;
/** Shared, locked dispatch for cron and manual sends. Test emails do not persist. */
class SendDispatcher
{
    public const LOCK_NAME = 'reviewautomation_review_send_emails';
    private const FIELDS = [
        SendResult::TYPE_EMAIL1 => 'email1_sent_at',
        SendResult::TYPE_EMAIL1_REMINDER => 'email1_reminder_sent_at',
        SendResult::TYPE_EMAIL2 => 'email2_sent_at',
        SendResult::TYPE_NUDGE => 'email2_nudge_sent_at',
        SendResult::TYPE_PRODUCT_REVIEW => 'product_review_sent_at',
    ];
    public function __construct(
        private readonly SatisfactionEmail $satisfactionEmail,
        private readonly SatisfactionReminderEmail $satisfactionReminderEmail,
        private readonly ReviewRequestEmail $reviewRequestEmail,
        private readonly ProductReviewRequestEmail $productReviewRequestEmail,
        private readonly QueueResource $queueResource,
        private readonly DateTime $dateTime,
        private readonly LockManagerInterface $locks,
        private readonly SendGuard $guard
    ) {}
    public function getNextEmailType(string $status): ?string
    {
        return match ($status) {
            Queue::STATUS_PENDING => SendResult::TYPE_EMAIL1,
            Queue::STATUS_EMAIL1_SENT => SendResult::TYPE_EMAIL1_REMINDER,
            Queue::STATUS_EMAIL1_REMINDER_SENT, Queue::STATUS_CLICKED_HAPPY,
            Queue::STATUS_CLICKED_UNHAPPY, Queue::STATUS_FEEDBACK_RECEIVED => SendResult::TYPE_EMAIL2,
            Queue::STATUS_EMAIL2_SENT => SendResult::TYPE_PRODUCT_REVIEW,
            default => null,
        };
    }
    public function dispatch(Queue $queue, bool $persist = true, ?string $type = null): SendResult
    {
        if ($persist && !$this->locks->lock(self::LOCK_NAME, 0)) {
            return SendResult::notSendable('Another send is in progress. Try again later.');
        }
        try {
            if ($persist) {
                $id = (int) $queue->getId();
                if (!$id) { return SendResult::notSendable('Queue entry missing.'); }
                $this->queueResource->load($queue, $id);
                if (!$queue->getId()) { return SendResult::notSendable('Queue entry missing.'); }
            }
            $type ??= $this->getNextEmailType((string) $queue->getStatus());
            if ($type === null || !isset(self::FIELDS[$type])) { return SendResult::notSendable('No message available.'); }
            $field = self::FIELDS[$type];
            if ($persist) {
                $reason = $this->guard->reason($queue, $type);
                if ($reason !== null) { return SendResult::notSendable($reason); }
                if ($queue->getData($field)) { return SendResult::notSendable('This message was already sent.'); }
                if ($type !== SendResult::TYPE_EMAIL1 && !$queue->getData('email1_sent_at')) {
                    return SendResult::notSendable('Send the satisfaction email first.');
                }
                if (in_array($type, [SendResult::TYPE_NUDGE, SendResult::TYPE_PRODUCT_REVIEW], true) && !$queue->getData('email2_sent_at')) {
                    return SendResult::notSendable('Send the company review request first.');
                }
                if ($type === SendResult::TYPE_EMAIL1_REMINDER && ($queue->getSentiment() || $queue->getData('email2_sent_at'))) {
                    return SendResult::notSendable('Satisfaction reminder no longer applicable.');
                }
                if ($type === SendResult::TYPE_NUDGE && $queue->getData('platform_click_at')) {
                    return SendResult::notSendable('Review platform already visited.');
                }
            }
            if ($type === SendResult::TYPE_PRODUCT_REVIEW && !$this->productReviewRequestEmail->hasReviewableProducts($queue)) {
                return SendResult::notSendable('No reviewable products found.');
            }
            $ok = match ($type) {
                SendResult::TYPE_EMAIL1 => $this->satisfactionEmail->send($queue),
                SendResult::TYPE_EMAIL1_REMINDER => $this->satisfactionReminderEmail->send($queue),
                SendResult::TYPE_EMAIL2 => $this->reviewRequestEmail->send($queue),
                SendResult::TYPE_NUDGE => $this->reviewRequestEmail->send($queue, true),
                SendResult::TYPE_PRODUCT_REVIEW => $this->productReviewRequestEmail->send($queue),
            };
            if (!$ok) { return SendResult::failure($type, 'Mail transport failed; see system.log.'); }
            if ($persist) {
                $queue->setData($field, $this->dateTime->gmtDate());
                if ($type !== SendResult::TYPE_NUDGE) {
                    $queue->setStatus(match ($type) {
                        SendResult::TYPE_EMAIL1 => Queue::STATUS_EMAIL1_SENT,
                        SendResult::TYPE_EMAIL1_REMINDER => Queue::STATUS_EMAIL1_REMINDER_SENT,
                        SendResult::TYPE_EMAIL2 => Queue::STATUS_EMAIL2_SENT,
                        SendResult::TYPE_PRODUCT_REVIEW => Queue::STATUS_PRODUCT_REVIEW_SENT,
                    });
                }
                $this->queueResource->save($queue);
            }
            return SendResult::success($type);
        } catch (\Throwable $e) {
            return SendResult::failure($type, $e->getMessage());
        } finally {
            if ($persist) { $this->locks->unlock(self::LOCK_NAME); }
        }
    }
}
