<?php
/**
 * Save handler for feedback admin_notes and is_resolved.
 */
declare(strict_types=1);

namespace ReviewAutomation\Reviews\Controller\Adminhtml\Feedback;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use ReviewAutomation\Reviews\Model\FeedbackFactory;
use ReviewAutomation\Reviews\Model\ResourceModel\Feedback as FeedbackResource;

class Save extends Action implements \Magento\Framework\App\Action\HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'ReviewAutomation_Reviews::feedback';

    public function __construct(
        Context $context,
        private readonly FeedbackFactory $feedbackFactory,
        private readonly FeedbackResource $feedbackResource
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $id = (int) $this->getRequest()->getParam('feedback_id');
        $redirect = $this->resultRedirectFactory->create();

        if (!$id) {
            $this->messageManager->addErrorMessage(__('Missing feedback ID.'));
            return $redirect->setPath('*/*/');
        }

        $feedback = $this->feedbackFactory->create();
        $this->feedbackResource->load($feedback, $id);
        if (!$feedback->getId()) {
            $this->messageManager->addErrorMessage(__('This feedback no longer exists.'));
            return $redirect->setPath('*/*/');
        }

        try {
            $feedback->setData('is_resolved', (bool) $this->getRequest()->getParam('is_resolved', 0));
            $feedback->setData('admin_notes', (string) $this->getRequest()->getParam('admin_notes', ''));
            $this->feedbackResource->save($feedback);
            $this->messageManager->addSuccessMessage(__('Feedback updated.'));
        } catch (\Throwable $e) {
            $this->messageManager->addErrorMessage(__('Error saving feedback: %1', $e->getMessage()));
            return $redirect->setPath('*/*/edit', ['id' => $id]);
        }

        return $redirect->setPath('*/*/');
    }
}
