<?php
/**
 * Dashboard statistics block displayed above the Review Queue grid.
 *
 * Runs a single aggregate query against `reviewautomation_review_queue` and exposes
 * the headline KPIs: total Email 1 sent, happy/unhappy/no-response rates,
 * Email 2 sent count, product review count, and total feedbacks received.
 */
declare(strict_types=1);

namespace ReviewAutomation\Reviews\Block\Adminhtml\Queue;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\App\ResourceConnection;
use ReviewAutomation\Reviews\Model\Queue;

class Stats extends Template
{
    private ?array $stats = null;

    public function __construct(
        Context $context,
        private readonly ResourceConnection $resourceConnection,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * All stats in one pass — avoids N+1 queries.
     */
    private function loadStats(): array
    {
        if ($this->stats !== null) {
            return $this->stats;
        }

        $connection = $this->resourceConnection->getConnection();
        $queueTable = $this->resourceConnection->getTableName('reviewautomation_review_queue');
        $feedbackTable = $this->resourceConnection->getTableName('reviewautomation_review_feedback');

        // Some historical rows advanced status without persisting sent-at.
        // Treat later funnel states as proof the earlier email was sent.
        $email1StatusList = implode(',', array_map(
            static fn (string $status): string => $connection->quote($status),
            [
                Queue::STATUS_EMAIL1_SENT,
                Queue::STATUS_EMAIL1_REMINDER_SENT,
                Queue::STATUS_CLICKED_HAPPY,
                Queue::STATUS_CLICKED_UNHAPPY,
                Queue::STATUS_EMAIL2_SENT,
                Queue::STATUS_PRODUCT_REVIEW_SENT,
                Queue::STATUS_PRODUCT_REVIEW_SKIPPED,
                Queue::STATUS_FEEDBACK_RECEIVED,
            ]
        ));
        $sentimentList = implode(',', array_map(
            static fn (string $sentiment): string => $connection->quote($sentiment),
            [
                Queue::SENTIMENT_HAPPY,
                Queue::SENTIMENT_UNHAPPY,
            ]
        ));

        $row = $connection->fetchRow(
            $connection->select()->from($queueTable, [
                'total'                => new \Zend_Db_Expr('COUNT(*)'),
                'email1_sent'          => new \Zend_Db_Expr(sprintf(
                    'SUM(CASE WHEN email1_sent_at IS NOT NULL OR status IN (%s) OR sentiment IN (%s) THEN 1 ELSE 0 END)',
                    $email1StatusList,
                    $sentimentList
                )),
                'clicked_happy'        => new \Zend_Db_Expr("SUM(CASE WHEN sentiment = 'happy' THEN 1 ELSE 0 END)"),
                'clicked_unhappy'      => new \Zend_Db_Expr("SUM(CASE WHEN sentiment = 'unhappy' THEN 1 ELSE 0 END)"),
                'email1_reminder_sent' => new \Zend_Db_Expr(sprintf(
                    'SUM(CASE WHEN email1_reminder_sent_at IS NOT NULL OR status = %s THEN 1 ELSE 0 END)',
                    $connection->quote(Queue::STATUS_EMAIL1_REMINDER_SENT)
                )),
                'email2_sent'          => new \Zend_Db_Expr(sprintf(
                    'SUM(CASE WHEN email2_sent_at IS NOT NULL OR status IN (%s, %s, %s) THEN 1 ELSE 0 END)',
                    $connection->quote(Queue::STATUS_EMAIL2_SENT),
                    $connection->quote(Queue::STATUS_PRODUCT_REVIEW_SENT),
                    $connection->quote(Queue::STATUS_PRODUCT_REVIEW_SKIPPED)
                )),
                'product_review_sent'  => new \Zend_Db_Expr(sprintf(
                    'SUM(CASE WHEN product_review_sent_at IS NOT NULL OR status = %s THEN 1 ELSE 0 END)',
                    $connection->quote(Queue::STATUS_PRODUCT_REVIEW_SENT)
                )),
                'platform_clicks'      => new \Zend_Db_Expr(
                    'SUM(CASE WHEN platform_click_at IS NOT NULL THEN 1 ELSE 0 END)'
                ),
            ])
        );

        $feedbackCount = (int) $connection->fetchOne(
            $connection->select()->from($feedbackTable, ['COUNT(*)'])
        );

        $email1Sent = (int) ($row['email1_sent'] ?? 0);
        $happy      = (int) ($row['clicked_happy'] ?? 0);
        $unhappy    = (int) ($row['clicked_unhappy'] ?? 0);
        $clicked    = $happy + $unhappy;
        $noResponse = max(0, $email1Sent - $clicked);
        $platformClicks = (int) ($row['platform_clicks'] ?? 0);

        $this->stats = [
            'total'                => (int) ($row['total'] ?? 0),
            'email1_sent'          => $email1Sent,
            'clicked_happy'        => $happy,
            'clicked_unhappy'      => $unhappy,
            'no_response'          => $noResponse,
            'email1_reminder_sent' => (int) ($row['email1_reminder_sent'] ?? 0),
            'email2_sent'          => (int) ($row['email2_sent'] ?? 0),
            'product_review_sent'  => (int) ($row['product_review_sent'] ?? 0),
            'feedback_count'       => $feedbackCount,
            'platform_clicks'      => $platformClicks,
            'platform_click_rate'  => $happy > 0 ? round(($platformClicks / $happy) * 100, 1) : 0,
            'happy_rate'           => $email1Sent > 0 ? round(($happy / $email1Sent) * 100, 1) : 0,
            'unhappy_rate'         => $email1Sent > 0 ? round(($unhappy / $email1Sent) * 100, 1) : 0,
            'no_response_rate'     => $email1Sent > 0 ? round(($noResponse / $email1Sent) * 100, 1) : 0,
        ];

        return $this->stats;
    }

    public function getTotalQueued(): int          { return $this->loadStats()['total']; }
    public function getEmail1Sent(): int           { return $this->loadStats()['email1_sent']; }
    public function getClickedHappy(): int         { return $this->loadStats()['clicked_happy']; }
    public function getClickedUnhappy(): int       { return $this->loadStats()['clicked_unhappy']; }
    public function getNoResponse(): int           { return $this->loadStats()['no_response']; }
    public function getEmail1ReminderSent(): int   { return $this->loadStats()['email1_reminder_sent']; }
    public function getEmail2Sent(): int           { return $this->loadStats()['email2_sent']; }
    public function getProductReviewSent(): int    { return $this->loadStats()['product_review_sent']; }
    public function getFeedbackCount(): int        { return $this->loadStats()['feedback_count']; }
    public function getPlatformClicks(): int       { return $this->loadStats()['platform_clicks']; }
    public function getPlatformClickRate(): float  { return $this->loadStats()['platform_click_rate']; }
    public function getHappyRate(): float          { return $this->loadStats()['happy_rate']; }
    public function getUnhappyRate(): float        { return $this->loadStats()['unhappy_rate']; }
    public function getNoResponseRate(): float     { return $this->loadStats()['no_response_rate']; }
}
