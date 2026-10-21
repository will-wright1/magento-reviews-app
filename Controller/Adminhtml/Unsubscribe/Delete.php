<?php
/**
 * Single-row delete from the suppression admin grid.
 *
 * Goes through SuppressionService::resume() so the side effect rules stay
 * consistent (delete the row; do NOT un-park existing queue rows).
 */
declare(strict_types=1);

namespace ReviewAutomation\Reviews\Controller\Adminhtml\Unsubscribe;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;
use ReviewAutomation\Reviews\Model\ResourceModel\Unsubscribe as UnsubscribeResource;
use ReviewAutomation\Reviews\Model\SuppressionService;
use ReviewAutomation\Reviews\Model\UnsubscribeFactory;

class Delete extends Action
{
    public const ADMIN_RESOURCE = 'ReviewAutomation_Reviews::unsubscribe';

    public function __construct(
        Context $context,
        private readonly UnsubscribeFactory $unsubscribeFactory,
        private readonly UnsubscribeResource $unsubscribeResource,
        private readonly SuppressionService $suppressionService,
        private readonly \Magento\Framework\Data\Form\FormKey $formKey
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $redirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        $id = (int) $this->getRequest()->getParam('id');
        if ($id <= 0) {
            $this->messageManager->addErrorMessage(__('Invalid suppression entry ID.'));
            return $redirect->setPath('*/*/');
        }

        try {
            $unsub = $this->unsubscribeFactory->create();
            $this->unsubscribeResource->load($unsub, $id);
            if (!$unsub->getId()) {
                throw new \RuntimeException((string) __('This suppression entry no longer exists.'));
            }
            if (!$this->getRequest()->isPost()) {
                $esc = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $action = $esc($this->getUrl('*/*/delete', ['id' => $id]));
                $key = $esc($this->formKey->getFormKey());
                $email = $esc((string) $unsub->getData('customer_email'));
                $back = $esc($this->getUrl('*/*/index'));
                return $this->resultFactory->create(ResultFactory::TYPE_RAW)->setContents(
                    '<h1>Remove suppression?</h1><p>' . $email . ' will be eligible for future order campaigns.</p>'
                    . '<form method="post" action="' . $action . '"><input type="hidden" name="form_key" value="' . $key . '">'
                    . '<button type="submit">Remove suppression</button></form><p><a href="' . $back . '">Cancel</a></p>'
                );
            }
            $this->suppressionService->resume((string) $unsub->getData('customer_email'));
            $this->messageManager->addSuccessMessage(__('Suppression entry deleted.'));
        } catch (\Throwable $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }

        return $redirect->setPath('*/*/');
    }
}
