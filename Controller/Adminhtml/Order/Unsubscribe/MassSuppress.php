<?php
/**
 * Mass action handler — invoked from the sales order grid.
 *
 * Loads the selected orders, deduplicates by customer_email, and pushes each
 * unique email through SuppressionService. Reports a clear added/skipped
 * count to the admin in the success message.
 *
 * The bulk action applies to each unique selected email.
 */
declare(strict_types=1);

namespace ReviewAutomation\Reviews\Controller\Adminhtml\Order\Unsubscribe;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\Auth\Session as AuthSession;
use Magento\Framework\Controller\ResultFactory;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;
use Magento\Ui\Component\MassAction\Filter;
use Psr\Log\LoggerInterface;
use ReviewAutomation\Reviews\Model\SuppressionService;

class MassSuppress extends Action implements \Magento\Framework\App\Action\HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'ReviewAutomation_Reviews::unsubscribe';

    public function __construct(
        Context $context,
        private readonly Filter $filter,
        private readonly OrderCollectionFactory $orderCollectionFactory,
        private readonly SuppressionService $suppressionService,
        private readonly AuthSession $authSession,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $redirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        try {
            $collection = $this->filter->getCollection($this->orderCollectionFactory->create());
        } catch (\Throwable $e) {
            $this->messageManager->addErrorMessage(__('Could not load the selected orders.'));
            $this->logger->error('[ReviewAutomation/MassSuppress] Filter failure: ' . $e->getMessage());
            return $redirect->setPath('sales/order/index');
        }

        // Dedupe — multiple orders may belong to the same customer.
        $emails = [];
        foreach ($collection as $order) {
            $email = strtolower(trim((string) $order->getCustomerEmail()));
            if ($email !== '' && !isset($emails[$email])) {
                $emails[$email] = true;
            }
        }

        $adminUserId = (int) ($this->authSession->getUser()?->getId() ?? 0) ?: null;
        $added = 0;
        $alreadySuppressed = 0;
        $failed = 0;

        foreach (array_keys($emails) as $email) {
            try {
                $inserted = $this->suppressionService->suppress(
                    $email,
                    SuppressionService::SOURCE_ADMIN_ORDER_GRID,
                    $adminUserId,
                    null
                );
                if ($inserted) {
                    $added++;
                } else {
                    $alreadySuppressed++;
                }
            } catch (\Throwable $e) {
                $failed++;
                $this->logger->warning(sprintf(
                    '[ReviewAutomation/MassSuppress] Failed to suppress %s: %s',
                    $email,
                    $e->getMessage()
                ));
            }
        }

        $this->messageManager->addSuccessMessage(
            __('Review email suppression: %1 added, %2 already suppressed.', $added, $alreadySuppressed)
        );
        if ($failed > 0) {
            $this->messageManager->addWarningMessage(
                __('%1 entries could not be suppressed — check the log for details.', $failed)
            );
        }

        return $redirect->setPath('sales/order/index');
    }
}
