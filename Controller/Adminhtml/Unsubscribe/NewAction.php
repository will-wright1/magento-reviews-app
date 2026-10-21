<?php
/**
 * Renders the "Add new suppression" form. Forwards to Edit::execute() so we
 * have a single render code path for both create and edit (matches Magento
 * core convention — see e.g. Magento\Catalog\Controller\Adminhtml\Product\NewAction).
 */
declare(strict_types=1);

namespace ReviewAutomation\Reviews\Controller\Adminhtml\Unsubscribe;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;

class NewAction extends Action
{
    public const ADMIN_RESOURCE = 'ReviewAutomation_Reviews::unsubscribe';

    public function execute()
    {
        $resultForward = $this->resultFactory->create(ResultFactory::TYPE_FORWARD);
        $resultForward->forward('edit');
        return $resultForward;
    }
}
