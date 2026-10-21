<?php

declare(strict_types=1);

namespace ReviewAutomation\Reviews\Controller\Response;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Psr\Log\LoggerInterface;
use ReviewAutomation\Reviews\Model\TimestampValidator;
use ReviewAutomation\Reviews\Model\Email\AdminFeedbackNotification;
use ReviewAutomation\Reviews\Model\Feedback;
use ReviewAutomation\Reviews\Model\FeedbackFactory;
use ReviewAutomation\Reviews\Model\Queue;
use ReviewAutomation\Reviews\Model\ResourceModel\Feedback as FeedbackResource;
use ReviewAutomation\Reviews\Model\ResourceModel\Queue as QueueResource;
use ReviewAutomation\Reviews\Model\TokenLookup;

class Feedbackpost implements HttpPostActionInterface, CsrfAwareActionInterface
{
    private const HONEYPOT_FIELD = 'website';
    private const TIMESTAMP_FIELD = 'form_token';

    public function __construct(
        private readonly RequestInterface $request,
        private readonly RedirectFactory $redirectFactory,
        private readonly FormKeyValidator $formKeyValidator,
        private readonly TimestampValidator $timestampValidator,
        private readonly TokenLookup $tokenLookup,
        private readonly FeedbackFactory $feedbackFactory,
        private readonly FeedbackResource $feedbackResource,
        private readonly QueueResource $queueResource,
        private readonly AdminFeedbackNotification $adminNotification,
        private readonly ManagerInterface $messageManager,
        private readonly DateTime $dateTime,
        private readonly LoggerInterface $logger,
        private readonly \Magento\Framework\App\ResourceConnection $resourceConnection,
        private readonly \Magento\Framework\Lock\LockManagerInterface $locks,
        private readonly \ReviewAutomation\Reviews\Model\SuppressionService $suppression
    ) {
    }

    public function execute(): ResultInterface
    {
        $redirect = $this->redirectFactory->create();
        $token = (string) $this->request->getParam('token');

        if (!$this->formKeyValidator->validate($this->request)) {
            return $redirect->setPath('reviewautomation_reviews/response/expired');
        }

        // --- Anti-spam checks ---

        // Honeypot: bots fill the hidden "website" field.
        $honeypot = $this->request->getParam(self::HONEYPOT_FIELD);
        if (!empty($honeypot)) {
            // Fake success so the bot doesn't retry.
            $this->messageManager->addSuccessMessage(
                __('Thank you — our team will review your feedback.')
            );
            return $redirect->setPath('reviewautomation_reviews/response/feedback', ['token' => $token]);
        }

        // Encrypted-timestamp token: rejects instant submissions and replays.
        $tsToken = $this->request->getParam(self::TIMESTAMP_FIELD);
        if (!$this->timestampValidator->validateToken($tsToken)) {
            $this->messageManager->addErrorMessage(
                __('Something went wrong. Please try again.')
            );
            return $redirect->setPath('reviewautomation_reviews/response/feedback', ['token' => $token]);
        }

        // --- Token + queue lookup ---
        $queue = $this->tokenLookup->loadByToken($token);
        if ($queue === null) {
            return $redirect->setPath('reviewautomation_reviews/response/expired');
        }

        if (in_array($queue->getStatus(), [Queue::STATUS_UNSUBSCRIBED, Queue::STATUS_SUPPRESSED], true)) {
            return $redirect->setPath('reviewautomation_reviews/response/expired');
        }

        // Prevent duplicate submissions.
        if ((string) $queue->getStatus() === Queue::STATUS_FEEDBACK_RECEIVED) {
            $this->messageManager->addSuccessMessage(
                __('We already have your feedback — thank you.')
            );
            return $redirect->setPath('reviewautomation_reviews/response/feedbackthankyou', ['token' => $token]);
        }

        // --- Persist feedback ---
        $feedbackText = trim((string) $this->request->getParam('feedback_text'));
        $rating = $this->request->getParam('rating');
        $ratingInt = ($rating !== null && $rating !== '' && (int) $rating >= 1 && (int) $rating <= 5)
            ? (int) $rating
            : null;

        if ($feedbackText === '' || strlen($feedbackText) > 10000) {
            $this->messageManager->addErrorMessage(
                __('Please enter feedback of up to 10,000 bytes.')
            );
            return $redirect->setPath('reviewautomation_reviews/response/feedback', ['token' => $token]);
        }

        if (!$this->locks->lock(\ReviewAutomation\Reviews\Model\Email\SendDispatcher::LOCK_NAME, 0)) {
            $this->messageManager->addErrorMessage(__('Please try again in a moment.'));
            return $redirect->setPath('reviewautomation_reviews/response/feedback', ['token' => $token]);
        }
        $db = $this->resourceConnection->getConnection();
        try {
            $this->queueResource->load($queue, (int) $queue->getId());
            if ($this->suppression->isSuppressed((string) $queue->getCustomerEmail())) {
                return $redirect->setPath('reviewautomation_reviews/response/expired');
            }
            $existing = $db->fetchOne($db->select()->from($this->resourceConnection->getTableName('reviewautomation_review_feedback'), ['COUNT(*)'])
                ->where('queue_id = ?', (int) $queue->getId()));
            if ((int) $existing > 0) {
                return $redirect->setPath('reviewautomation_reviews/response/feedbackthankyou', ['token' => $token]);
            }
            $db->beginTransaction();
            $feedback = $this->feedbackFactory->create();
            $feedback->setData([
                'queue_id'           => (int) $queue->getId(),
                'order_id'           => (int) $queue->getOrderId(),
                'order_increment_id' => (string) $queue->getOrderIncrementId(),
                'customer_email'     => (string) $queue->getCustomerEmail(),
                'customer_firstname' => (string) $queue->getCustomerFirstname(),
                'feedback_text'      => $feedbackText,
                'rating'             => $ratingInt,
                'is_resolved'        => 0,
                'created_at'         => $this->dateTime->gmtDate(),
            ]);
            $this->feedbackResource->save($feedback);

            if (!$queue->getData('email2_sent_at')) {
                $queue->setStatus(Queue::STATUS_FEEDBACK_RECEIVED);
            }
            $this->queueResource->save($queue);
            $db->commit();

            // Fire-and-forget admin notification — don't let a mail failure
            // block the customer-facing success response.
            try {
                $this->adminNotification->send($feedback, (int) $queue->getStoreId());
            } catch (\Throwable $e) {
                $this->logger->error(
                    'ReviewAutomation: Admin feedback notification failed: ' . $e->getMessage()
                );
            }

            $this->messageManager->addSuccessMessage(
                __('Thank you — our team will be in touch if we need any more details.')
            );
        } catch (\Throwable $e) {
            if ($db->getTransactionLevel() > 0) { $db->rollBack(); }
            $this->logger->error('ReviewAutomation: Feedback save error: ' . $e->getMessage());
            $this->messageManager->addErrorMessage(
                __('Something went wrong saving your feedback. Please try again.')
            );
            return $redirect->setPath('reviewautomation_reviews/response/feedback', ['token' => $token]);
        } finally {
            $this->locks->unlock(\ReviewAutomation\Reviews\Model\Email\SendDispatcher::LOCK_NAME);
        }

        return $redirect->setPath('reviewautomation_reviews/response/feedbackthankyou', ['token' => $token]);
    }


    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }


    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return $this->formKeyValidator->validate($request);
    }
}
