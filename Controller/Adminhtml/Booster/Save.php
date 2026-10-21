<?php
/**
 * Create a new Review Booster batch from the admin form.
 */
declare(strict_types=1);

namespace ReviewAutomation\Reviews\Controller\Adminhtml\Booster;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\Auth\Session as AdminSession;
use Magento\Framework\App\Action\HttpPostActionInterface;
use ReviewAutomation\Reviews\Model\BoosterBatch;
use ReviewAutomation\Reviews\Model\BoosterBatchFactory;
use ReviewAutomation\Reviews\Model\ResourceModel\BoosterBatch as BoosterBatchResource;

class Save extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'ReviewAutomation_Reviews::booster';

    private const MAX_PER_DAY = 500;

    public function __construct(
        Context $context,
        private readonly BoosterBatchFactory $batchFactory,
        private readonly BoosterBatchResource $batchResource,
        private readonly AdminSession $adminSession
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $redirect = $this->resultRedirectFactory->create()->setPath('*/*/index');

        $dateFrom = (string) $this->getRequest()->getParam('date_from');
        $dateTo = (string) $this->getRequest()->getParam('date_to');
        $minValue = max(0.0, (float) $this->getRequest()->getParam('min_order_value', 0));
        $perDay = (int) $this->getRequest()->getParam('per_day', 50);

        if (!$this->isValidDate($dateFrom) || !$this->isValidDate($dateTo) || $dateFrom > $dateTo) {
            $this->messageManager->addErrorMessage(__('Please pick a valid date range.'));
            return $redirect;
        }

        if ($perDay < 1 || $perDay > self::MAX_PER_DAY) {
            $this->messageManager->addErrorMessage(
                __('Emails per day must be between 1 and %1.', self::MAX_PER_DAY)
            );
            return $redirect;
        }

        try {
            $adminUser = $this->adminSession->getUser();

            /** @var BoosterBatch $batch */
            $batch = $this->batchFactory->create();
            $batch->setData([
                'status'          => BoosterBatch::STATUS_ACTIVE,
                'date_from'       => $dateFrom,
                'date_to'         => $dateTo,
                'min_order_value' => $minValue,
                'per_day'         => $perDay,
                'total_queued'    => 0,
                'created_by'      => $adminUser ? (string) $adminUser->getUserName() : null,
            ]);
            $this->batchResource->save($batch);

            $this->messageManager->addSuccessMessage(__(
                'Booster batch #%1 created. Up to %2 customers per day will be drip-fed into the review queue starting within the hour.',
                (int) $batch->getId(),
                $perDay
            ));
        } catch (\Throwable $e) {
            $this->messageManager->addErrorMessage(__('Could not create batch: %1', $e->getMessage()));
        }

        return $redirect;
    }

    private function isValidDate(string $value): bool
    {
        return (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) && strtotime($value) !== false;
    }
}
