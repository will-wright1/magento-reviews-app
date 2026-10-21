<?php
declare(strict_types=1);

namespace ReviewAutomation\Reviews\Model;

use Magento\Framework\Model\AbstractModel;
use ReviewAutomation\Reviews\Model\ResourceModel\Queue as QueueResource;

class Queue extends AbstractModel
{
    public const STATUS_PENDING              = 'pending';
    public const STATUS_EMAIL1_SENT          = 'email1_sent';
    public const STATUS_EMAIL1_REMINDER_SENT = 'email1_reminder_sent';
    public const STATUS_CLICKED_HAPPY        = 'clicked_happy';
    public const STATUS_CLICKED_UNHAPPY      = 'clicked_unhappy';
    public const STATUS_EMAIL2_SENT          = 'email2_sent';
    public const STATUS_PRODUCT_REVIEW_SENT  = 'product_review_sent';
    public const STATUS_PRODUCT_REVIEW_SKIPPED = 'product_review_skipped';
    public const STATUS_FEEDBACK_RECEIVED    = 'feedback_received';
    public const STATUS_UNSUBSCRIBED         = 'unsubscribed';
    public const STATUS_SUPPRESSED           = 'suppressed';

    public const SENTIMENT_HAPPY   = 'happy';
    public const SENTIMENT_UNHAPPY = 'unhappy';

    /**
     * @var string
     */
    protected $_eventPrefix = 'reviewautomation_review_queue';

    protected function _construct(): void
    {
        $this->_init(QueueResource::class);
    }
}
