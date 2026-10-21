<?php
/**
 * Block for the admin feedback detail/edit page.
 *
 * Loads the Feedback model and exposes its fields to the template, plus
 * builds the save URL and back URL for the form.
 */
declare(strict_types=1);

namespace ReviewAutomation\Reviews\Block\Adminhtml\Feedback;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use ReviewAutomation\Reviews\Model\Feedback;
use ReviewAutomation\Reviews\Model\FeedbackFactory;
use ReviewAutomation\Reviews\Model\ResourceModel\Feedback as FeedbackResource;

class Edit extends Template
{
    private ?Feedback $feedback = null;

    public function __construct(
        Context $context,
        private readonly FeedbackFactory $feedbackFactory,
        private readonly FeedbackResource $feedbackResource,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getFeedback(): Feedback
    {
        if ($this->feedback === null) {
            $this->feedback = $this->feedbackFactory->create();
            $id = (int) $this->getRequest()->getParam('id');
            if ($id) {
                $this->feedbackResource->load($this->feedback, $id);
            }
        }
        return $this->feedback;
    }

    public function getSaveUrl(): string
    {
        return $this->getUrl('*/*/save');
    }

    public function getBackUrl(): string
    {
        return $this->getUrl('*/*/');
    }

    public function getOrderUrl(): string
    {
        $orderId = (int) $this->getFeedback()->getOrderId();
        return $orderId ? $this->getUrl('sales/order/view', ['order_id' => $orderId]) : '#';
    }

    public function getRatingStars(): string
    {
        $rating = (int) $this->getFeedback()->getRating();
        if ($rating < 1 || $rating > 5) {
            return 'N/A';
        }
        return str_repeat('★', $rating) . str_repeat('☆', 5 - $rating);
    }
}
