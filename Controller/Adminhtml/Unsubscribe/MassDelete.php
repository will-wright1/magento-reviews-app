<?php
/**
 * Bulk delete from the suppression admin grid.
 *
 * Each row is removed via SuppressionService::resume() so the per-row side
 * effects stay consistent (no un-parking of historical queue rows).
 */
declare(strict_types=1);

namespace ReviewAutomation\Reviews\Controller\Adminhtml\Unsubscribe;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;
use Magento\Ui\Component\MassAction\Filter;
use Psr\Log\LoggerInterface;
use ReviewAutomation\Reviews\Model\ResourceModel\Unsubscribe\CollectionFactory;
use ReviewAutomation\Reviews\Model\SuppressionService;

class MassDelete extends Action implements \Magento\Framework\App\Action\HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'ReviewAutomation_Reviews::unsubscribe';

    public function __construct(
        Context $context,
        private readonly Filter $filter,
        private readonly CollectionFactory $collectionFactory,
        private readonly SuppressionService $suppressionService,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $redirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        $collection = $this->filter->getCollection($this->collectionFactory->create());

        $deleted = 0;
        $failed  = 0;
        foreach ($collection as $row) {
            try {
                $email = (string) $row->getData('customer_email');
                if ($email !== '' && $this->suppressionService->resume($email)) {
                    $deleted++;
                } else {
                    $failed++;
                }
            } catch (\Throwable $e) {
                $failed++;
                $this->logger->warning(sprintf(
                    '[ReviewAutomation/Unsubscribe/MassDelete] Failed to remove suppression %d: %s',
                    (int) $row->getId(),
                    $e->getMessage()
                ));
            }
        }

        $this->messageManager->addSuccessMessage(
            __('A total of %1 suppression entries deleted.', $deleted)
        );
        if ($failed > 0) {
            $this->messageManager->addWarningMessage(
                __('%1 entries could not be deleted — check the log for details.', $failed)
            );
        }

        return $redirect->setPath('*/*/');
    }
}
