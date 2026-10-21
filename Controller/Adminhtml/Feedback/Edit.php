<?php
/**
 * Feedback detail/edit page. Displays the full feedback text and lets the
 * admin update the "resolved" flag and add internal notes.
 */
declare(strict_types=1);

namespace ReviewAutomation\Reviews\Controller\Adminhtml\Feedback;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;
use ReviewAutomation\Reviews\Model\FeedbackFactory;
use ReviewAutomation\Reviews\Model\ResourceModel\Feedback as FeedbackResource;

class Edit extends Action
{
    public const ADMIN_RESOURCE = 'ReviewAutomation_Reviews::feedback';

    public function __construct(
        Context $context,
        private readonly PageFactory $pageFactory,
        private readonly FeedbackFactory $feedbackFactory,
        private readonly FeedbackResource $feedbackResource
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $id = (int) $this->getRequest()->getParam('id');
        $feedback = $this->feedbackFactory->create();
        if ($id) {
            $this->feedbackResource->load($feedback, $id);
            if (!$feedback->getId()) {
                $this->messageManager->addErrorMessage(__('This feedback no longer exists.'));
                return $this->resultRedirectFactory->create()->setPath('*/*/');
            }
        }

        $page = $this->pageFactory->create();
        $page->setActiveMenu('ReviewAutomation_Reviews::feedback');
        $page->getConfig()->getTitle()->prepend(
            $feedback->getId()
                ? __('Feedback #%1 — Order #%2', $feedback->getId(), $feedback->getOrderIncrementId())
                : __('Feedback')
        );
        return $page;
    }
}
