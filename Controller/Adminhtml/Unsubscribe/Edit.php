<?php
/**
 * Renders the suppression-row form: new entry (no id) or edit (with id).
 *
 * For edits, only the `reason` field is editable per spec — customer_email,
 * source, created_by_admin_user_id and created_at are immutable. If admin
 * needs a different email, they delete and re-add. The form UI enforces
 * this; this controller is just the page render.
 */
declare(strict_types=1);

namespace ReviewAutomation\Reviews\Controller\Adminhtml\Unsubscribe;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Registry;
use Magento\Framework\View\Result\PageFactory;
use ReviewAutomation\Reviews\Model\ResourceModel\Unsubscribe as UnsubscribeResource;
use ReviewAutomation\Reviews\Model\UnsubscribeFactory;

class Edit extends Action
{
    public const ADMIN_RESOURCE = 'ReviewAutomation_Reviews::unsubscribe';

    public function __construct(
        Context $context,
        private readonly PageFactory $pageFactory,
        private readonly Registry $registry,
        private readonly UnsubscribeFactory $unsubscribeFactory,
        private readonly UnsubscribeResource $unsubscribeResource
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $id = (int) $this->getRequest()->getParam('id');
        $unsub = $this->unsubscribeFactory->create();

        if ($id) {
            $this->unsubscribeResource->load($unsub, $id);
            if (!$unsub->getId()) {
                $this->messageManager->addErrorMessage(__('This suppression entry no longer exists.'));
                return $this->resultRedirectFactory->create()->setPath('*/*/');
            }
        }

        // Form data provider reads from registry — same pattern as core CRUDs.
        $this->registry->register('reviewautomation_review_unsubscribe_current', $unsub);

        $page = $this->pageFactory->create();
        $page->setActiveMenu('ReviewAutomation_Reviews::unsubscribe');
        $page->getConfig()->getTitle()->prepend(
            $id ? __('Edit Suppression Entry') : __('Add Suppression Entry')
        );
        return $page;
    }
}
