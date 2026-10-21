<?php
/**
 * Mass action: mark selected feedback rows as resolved.
 */
declare(strict_types=1);

namespace ReviewAutomation\Reviews\Controller\Adminhtml\Feedback;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Ui\Component\MassAction\Filter;
use ReviewAutomation\Reviews\Model\ResourceModel\Feedback\CollectionFactory;

class MassResolve extends Action implements \Magento\Framework\App\Action\HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'ReviewAutomation_Reviews::feedback';

    public function __construct(
        Context $context,
        private readonly Filter $filter,
        private readonly CollectionFactory $collectionFactory
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $collection = $this->filter->getCollection($this->collectionFactory->create());
        $updated = 0;

        foreach ($collection as $feedback) {
            $feedback->setData('is_resolved', 1);
            $feedback->save();
            $updated++;
        }

        $this->messageManager->addSuccessMessage(
            __('A total of %1 feedback(s) have been marked as resolved.', $updated)
        );

        return $this->resultRedirectFactory->create()->setPath('*/*/');
    }
}
