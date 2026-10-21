<?php
/**
 * Pause / resume / cancel a Review Booster batch.
 */
declare(strict_types=1);

namespace ReviewAutomation\Reviews\Controller\Adminhtml\Booster;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use ReviewAutomation\Reviews\Model\BoosterBatch;
use ReviewAutomation\Reviews\Model\BoosterBatchFactory;
use ReviewAutomation\Reviews\Model\ResourceModel\BoosterBatch as BoosterBatchResource;

class ChangeStatus extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'ReviewAutomation_Reviews::booster';

    /**
     * Allowed transitions keyed by requested action.
     */
    private const TRANSITIONS = [
        'pause'  => [BoosterBatch::STATUS_ACTIVE => BoosterBatch::STATUS_PAUSED],
        'resume' => [BoosterBatch::STATUS_PAUSED => BoosterBatch::STATUS_ACTIVE],
        'cancel' => [
            BoosterBatch::STATUS_ACTIVE => BoosterBatch::STATUS_CANCELLED,
            BoosterBatch::STATUS_PAUSED => BoosterBatch::STATUS_CANCELLED,
        ],
    ];

    public function __construct(
        Context $context,
        private readonly BoosterBatchFactory $batchFactory,
        private readonly BoosterBatchResource $batchResource
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $redirect = $this->resultRedirectFactory->create()->setPath('*/*/index');

        $batchId = (int) $this->getRequest()->getParam('batch_id');
        $action = (string) $this->getRequest()->getParam('batch_action');

        if (!isset(self::TRANSITIONS[$action])) {
            $this->messageManager->addErrorMessage(__('Unknown action.'));
            return $redirect;
        }

        $batch = $this->batchFactory->create();
        $this->batchResource->load($batch, $batchId);
        if (!$batch->getId()) {
            $this->messageManager->addErrorMessage(__('Batch not found.'));
            return $redirect;
        }

        $current = (string) $batch->getData('status');
        $next = self::TRANSITIONS[$action][$current] ?? null;
        if ($next === null) {
            $this->messageManager->addErrorMessage(
                __('Cannot %1 a batch that is %2.', $action, $current)
            );
            return $redirect;
        }

        try {
            $batch->setData('status', $next);
            $this->batchResource->save($batch);
            $this->messageManager->addSuccessMessage(__('Batch #%1 is now %2.', $batchId, $next));
        } catch (\Throwable $e) {
            $this->messageManager->addErrorMessage(__('Could not update batch: %1', $e->getMessage()));
        }

        return $redirect;
    }
}
