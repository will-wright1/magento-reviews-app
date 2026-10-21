<?php
/**
 * Block for the private-feedback form (unhappy path).
 *
 * Loads the Queue row by token so the template can display the customer's
 * first name and order number for context, and generates both the
 * honeypot timestamp token and the form-key hidden field.
 */
declare(strict_types=1);

namespace ReviewAutomation\Reviews\Block;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use ReviewAutomation\Reviews\Model\TimestampValidator;
use ReviewAutomation\Reviews\Model\Queue;
use ReviewAutomation\Reviews\Model\TokenLookup;

class FeedbackForm extends Template
{
    private ?Queue $queue = null;
    private bool $queueLoaded = false;

    public function __construct(
        Context $context,
        private readonly TokenLookup $tokenLookup,
        private readonly TimestampValidator $timestampValidator,
        private readonly RequestInterface $request,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getToken(): string
    {
        return (string) $this->request->getParam('token');
    }

    /**
     * Load the queue row once — shared between getCustomerFirstname() and
     * getOrderIncrementId().
     */
    private function getQueue(): ?Queue
    {
        if (!$this->queueLoaded) {
            $this->queue = $this->tokenLookup->loadByToken($this->getToken());
            $this->queueLoaded = true;
        }
        return $this->queue;
    }

    public function getCustomerFirstname(): string
    {
        $queue = $this->getQueue();
        return $queue ? (string) ($queue->getCustomerFirstname() ?: 'there') : 'there';
    }

    public function getOrderIncrementId(): string
    {
        $queue = $this->getQueue();
        return $queue ? (string) $queue->getOrderIncrementId() : '';
    }

    /**
     * Generate a fresh encrypted-timestamp token for anti-bot validation.
     */
    public function getTimestampToken(): string
    {
        return $this->timestampValidator->generateToken();
    }

    /**
     * Build the form action URL.
     */
    public function getFormAction(): string
    {
        return $this->getUrl('reviewautomation_reviews/response/feedbackpost', ['token' => $this->getToken()]);
    }
}
