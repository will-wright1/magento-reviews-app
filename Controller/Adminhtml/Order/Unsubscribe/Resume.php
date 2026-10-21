<?php
/**
 * Order-view "Resume" button handler.
 *
 * Removes the suppression row for the order's customer_email. Does NOT
 * un-park existing STATUS_UNSUBSCRIBED queue rows — those represent past
 * decisions and shouldn't be retroactively flipped. Resume only affects
 * future orders entering the queue.
 */
declare(strict_types=1);

namespace ReviewAutomation\Reviews\Controller\Adminhtml\Order\Unsubscribe;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use ReviewAutomation\Reviews\Model\SuppressionService;

class Resume extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'ReviewAutomation_Reviews::unsubscribe';

    public function __construct(
        Context $context,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly SuppressionService $suppressionService
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $redirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        $orderId = (int) $this->getRequest()->getParam('order_id');
        if ($orderId <= 0) {
            $this->messageManager->addErrorMessage(__('Invalid order ID.'));
            return $redirect->setPath('sales/order/index');
        }

        try {
            $order = $this->orderRepository->get($orderId);
        } catch (NoSuchEntityException $e) {
            $this->messageManager->addErrorMessage(__('Order not found.'));
            return $redirect->setPath('sales/order/index');
        }

        $email = strtolower(trim((string) $order->getCustomerEmail()));
        if ($email === '') {
            $this->messageManager->addErrorMessage(__('Order has no customer email.'));
            return $redirect->setPath('sales/order/view', ['order_id' => $orderId]);
        }

        try {
            $deleted = $this->suppressionService->resume($email);
            if ($deleted) {
                $this->messageManager->addSuccessMessage(__(
                    'Review emails resumed for %1. Note: any past orders already marked as unsubscribed in the queue will not be retroactively re-sent.',
                    $email
                ));
            } else {
                $this->messageManager->addWarningMessage(
                    __('%1 was not currently in the suppression list.', $email)
                );
            }
        } catch (\Throwable $e) {
            $this->messageManager->addErrorMessage(
                __('Could not resume: %1', $e->getMessage())
            );
        }

        return $redirect->setPath('sales/order/view', ['order_id' => $orderId]);
    }
}
