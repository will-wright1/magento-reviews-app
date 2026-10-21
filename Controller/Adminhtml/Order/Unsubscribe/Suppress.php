<?php
/**
 * Order-view "Suppress" button handler.
 *
 * Adds the order's customer_email to the suppression list with
 * source = admin_order_view and the optional reason from the prompt.
 * Parks any non-final queue rows for that email at STATUS_UNSUBSCRIBED.
 */
declare(strict_types=1);

namespace ReviewAutomation\Reviews\Controller\Adminhtml\Order\Unsubscribe;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\Auth\Session as AuthSession;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use ReviewAutomation\Reviews\Model\SuppressionService;

class Suppress extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'ReviewAutomation_Reviews::unsubscribe';

    public function __construct(
        Context $context,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly SuppressionService $suppressionService,
        private readonly AuthSession $authSession
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

        // Defensive server-side truncation. The native prompt() has no length
        // enforcement, the JS truncates client-side at 255 chars, but we can't
        // trust the client. mb_substr (not substr) is character-aware so a
        // multi-byte paste won't get sliced mid-codepoint into invalid UTF-8
        // before MySQL sees it. Column is varchar(255).
        $reasonRaw = $this->getRequest()->getParam('reason');
        $reason = $reasonRaw !== null ? mb_substr(trim((string) $reasonRaw), 0, 255) : null;
        if ($reason === '') {
            $reason = null;
        }
        $adminUserId = (int) ($this->authSession->getUser()?->getId() ?? 0) ?: null;

        try {
            $inserted = $this->suppressionService->suppress(
                $email,
                SuppressionService::SOURCE_ADMIN_ORDER_VIEW,
                $adminUserId,
                $reason
            );
            if ($inserted) {
                $this->messageManager->addSuccessMessage(
                    __('%1 added to the review email suppression list.', $email)
                );
            } else {
                $this->messageManager->addWarningMessage(
                    __('%1 is already in the suppression list.', $email)
                );
            }
        } catch (\Throwable $e) {
            $this->messageManager->addErrorMessage(
                __('Could not suppress this customer: %1', $e->getMessage())
            );
        }

        return $redirect->setPath('sales/order/view', ['order_id' => $orderId]);
    }
}
