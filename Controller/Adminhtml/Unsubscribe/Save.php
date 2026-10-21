<?php
/**
 * Saves a suppression-row form submission.
 *
 * For new rows: creates via SuppressionService::suppress() so the queue rows
 * get parked correctly and the source/admin_user_id/reason are recorded.
 *
 * For edits: only the `reason` field is mutable. customer_email, source,
 * created_by_admin_user_id, created_at are all preserved.
 *
 * Duplicate-email errors on new rows are reported as a friendly form error
 * rather than a stack trace.
 */
declare(strict_types=1);

namespace ReviewAutomation\Reviews\Controller\Adminhtml\Unsubscribe;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\Auth\Session as AuthSession;
use Magento\Framework\Controller\ResultFactory;
use ReviewAutomation\Reviews\Model\ResourceModel\Unsubscribe as UnsubscribeResource;
use ReviewAutomation\Reviews\Model\SuppressionService;
use ReviewAutomation\Reviews\Model\UnsubscribeFactory;

class Save extends Action implements \Magento\Framework\App\Action\HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'ReviewAutomation_Reviews::unsubscribe';

    public function __construct(
        Context $context,
        private readonly SuppressionService $suppressionService,
        private readonly UnsubscribeFactory $unsubscribeFactory,
        private readonly UnsubscribeResource $unsubscribeResource,
        private readonly AuthSession $authSession
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $redirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        $data = $this->getRequest()->getPostValue();
        if (empty($data)) {
            return $redirect->setPath('*/*/');
        }

        $id    = (int) ($data['unsubscribe_id'] ?? 0);
        $email = strtolower(trim((string) ($data['customer_email'] ?? '')));

        // Defensive server-side truncation — see Suppress.php for rationale.
        // The form has a max_text_length=255 validation rule but admins could
        // POST directly. mb_substr is character-aware (not byte-aware).
        $reasonRaw = $data['reason'] ?? null;
        $reason = $reasonRaw !== null ? mb_substr(trim((string) $reasonRaw), 0, 255) : null;
        if ($reason === '') {
            $reason = null;
        }

        try {
            if ($id > 0) {
                // Edit mode — only `reason` is mutable.
                $unsub = $this->unsubscribeFactory->create();
                $this->unsubscribeResource->load($unsub, $id);
                if (!$unsub->getId()) {
                    throw new \RuntimeException((string) __('This suppression entry no longer exists.'));
                }
                $unsub->setData('reason', $reason);
                $this->unsubscribeResource->save($unsub);
                $this->messageManager->addSuccessMessage(__('Suppression entry updated.'));
            } else {
                // Create mode — validate email and delegate to the service.
                if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    throw new \RuntimeException((string) __('Please provide a valid email address.'));
                }
                $adminUserId = (int) ($this->authSession->getUser()?->getId() ?? 0) ?: null;
                $inserted = $this->suppressionService->suppress(
                    $email,
                    SuppressionService::SOURCE_ADMIN_MANUAL,
                    $adminUserId,
                    $reason
                );
                if (!$inserted) {
                    throw new \RuntimeException((string) __('This email is already in the suppression list.'));
                }
                $this->messageManager->addSuccessMessage(
                    __('Customer email %1 added to the suppression list.', $email)
                );
            }
        } catch (\Throwable $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
            return $redirect->setPath('*/*/edit', ['id' => $id]);
        }

        return $redirect->setPath('*/*/');
    }
}
