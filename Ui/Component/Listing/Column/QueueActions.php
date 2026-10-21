<?php
/**
 * Per-row actions column for the Review Queue admin grid.
 *
 * Renders a "Send Now" link if SendDispatcher::getNextEmailType() agrees
 * the row is in a sendable status (pending -> Email 1, email1_sent ->
 * reminder, clicked_happy/unhappy -> Email 2, email2_sent -> product review).
 * For non-sendable statuses,
 * renders a disabled label with a tooltip explaining why.
 *
 * The link includes a confirm() dialog with email type + recipient. For
 * Email 2 sends, the dialog also flags that the email will request a
 * public review — extra context for the admin pulling the trigger.
 */
declare(strict_types=1);

namespace ReviewAutomation\Reviews\Ui\Component\Listing\Column;

use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;
use ReviewAutomation\Reviews\Model\Email\SendDispatcher;
use ReviewAutomation\Reviews\Model\Email\SendResult;

class QueueActions extends Column
{
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        private readonly UrlInterface $urlBuilder,
        private readonly SendDispatcher $sendDispatcher,
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    public function prepareDataSource(array $dataSource)
    {
        if (!isset($dataSource['data']['items'])) {
            return $dataSource;
        }

        $name = $this->getData('name');
        foreach ($dataSource['data']['items'] as &$row) {
            $queueId = (int) ($row['queue_id'] ?? 0);
            $status  = (string) ($row['status'] ?? '');
            $email   = (string) ($row['customer_email'] ?? '');
            if ($queueId <= 0) {
                continue;
            }

            $nextType = $this->sendDispatcher->getNextEmailType($status);

            if ($nextType === null) {
                // Non-sendable status — render a disabled label with tooltip.
                $row[$name] = [
                    'send_now_disabled' => [
                        'label'    => __('Send Now (n/a)'),
                        'href'     => '#',
                        'disabled' => true,
                        'hidden'   => false,
                    ],
                ];
                continue;
            }

            $row[$name] = [
                'send_now' => [
                    'href'  => $this->urlBuilder->getUrl(
                        'reviewautomation_reviewautomation/queue/sendNow',
                        ['queue_id' => $queueId]
                    ),
                    'label'   => $this->buildLabel($nextType),
                    'confirm' => [
                        'title'   => (string) __('Send %1 Now?', $this->emailTypeLabel($nextType)),
                        'message' => $this->buildConfirmMessage($nextType, $email),
                    ],
                ],
            ];
        }
        return $dataSource;
    }

    private function buildLabel(string $emailType): string
    {
        return match ($emailType) {
            SendResult::TYPE_PRODUCT_REVIEW => (string) __('Send Product Review Now'),
            SendResult::TYPE_EMAIL2 => (string) __('Send Email 2 Now'),
            SendResult::TYPE_EMAIL1_REMINDER => (string) __('Send Reminder Now'),
            default => (string) __('Send Email 1 Now'),
        };
    }

    private function emailTypeLabel(string $emailType): string
    {
        return match ($emailType) {
            SendResult::TYPE_PRODUCT_REVIEW => 'Product Review Request',
            SendResult::TYPE_EMAIL2 => 'Email 2',
            SendResult::TYPE_EMAIL1_REMINDER => 'Email 1 Reminder',
            default => 'Email 1',
        };
    }

    private function buildConfirmMessage(string $emailType, string $email): string
    {
        if ($emailType === SendResult::TYPE_EMAIL2) {
            // Extra context for Email 2 — it asks the customer for a public
            // review on Google/Trustpilot, so the admin should know they're
            // committing to that request, not just "another email".
            return (string) __(
                'Send Email 2 (Review Request) now to %1? This bypasses cron and the time-gate. Email 2 asks the customer for a public review on Google/Trustpilot.',
                $email
            );
        }
        if ($emailType === SendResult::TYPE_PRODUCT_REVIEW) {
            return (string) __(
                'Send the product review request now to %1? This bypasses cron and the configured time-gate. The email links to Magento product review forms for this order.',
                $email
            );
        }
        if ($emailType === SendResult::TYPE_EMAIL1_REMINDER) {
            return (string) __(
                'Send the Email 1 reminder now to %1? This bypasses cron and the reminder time-gate.',
                $email
            );
        }
        return (string) __(
            'Send Email 1 (Satisfaction Check) now to %1? This bypasses cron and the time-gate.',
            $email
        );
    }
}
