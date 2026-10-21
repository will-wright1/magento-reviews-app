<?php
/**
 * Block: order-view "Suppress / Resume review emails" toggle.
 *
 * Renders nothing if the admin lacks the suppression ACL or the order has
 * no customer_email. Otherwise renders an orange "Suppress" or green
 * "Resume" button depending on current state, with the suppression's
 * metadata (source/reason/created_at/admin) shown alongside the resume
 * button.
 */
declare(strict_types=1);

namespace ReviewAutomation\Reviews\Block\Adminhtml\Order\View;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\AuthorizationInterface;
use Magento\Framework\Registry;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\User\Model\UserFactory;
use ReviewAutomation\Reviews\Model\SuppressionService;
use ReviewAutomation\Reviews\Model\Unsubscribe;

class SuppressReviewButton extends Template
{
    public function __construct(
        Context $context,
        private readonly Registry $registry,
        private readonly AuthorizationInterface $authorization,
        private readonly SuppressionService $suppressionService,
        private readonly UserFactory $userFactory,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * True if this block should render at all (ACL + order has email).
     */
    public function canRender(): bool
    {
        if (!$this->authorization->isAllowed('ReviewAutomation_Reviews::unsubscribe')) {
            return false;
        }
        $order = $this->getOrder();
        return $order !== null && trim((string) $order->getCustomerEmail()) !== '';
    }

    public function getOrder(): ?OrderInterface
    {
        $order = $this->registry->registry('current_order') ?? $this->registry->registry('sales_order');
        return $order instanceof OrderInterface ? $order : null;
    }

    public function getCustomerEmail(): string
    {
        return strtolower(trim((string) ($this->getOrder()?->getCustomerEmail() ?? '')));
    }

    public function getCurrentSuppression(): ?Unsubscribe
    {
        $email = $this->getCustomerEmail();
        return $email !== '' ? $this->suppressionService->findByEmail($email) : null;
    }

    public function isSuppressed(): bool
    {
        return $this->getCurrentSuppression() !== null;
    }

    public function getSuppressUrl(): string
    {
        return $this->getUrl(
            'reviewautomation_reviewautomation/order_unsubscribe/suppress',
            ['order_id' => (int) ($this->getOrder()?->getEntityId() ?? 0)]
        );
    }

    public function getResumeUrl(): string
    {
        return $this->getUrl(
            'reviewautomation_reviewautomation/order_unsubscribe/resume',
            ['order_id' => (int) ($this->getOrder()?->getEntityId() ?? 0)]
        );
    }

    public function getSourceLabel(?string $source): string
    {
        return match ($source) {
            SuppressionService::SOURCE_SELF             => 'Customer self-unsubscribe',
            SuppressionService::SOURCE_ADMIN_ORDER_GRID => 'Admin (order grid mass action)',
            SuppressionService::SOURCE_ADMIN_ORDER_VIEW => 'Admin (order view page)',
            SuppressionService::SOURCE_ADMIN_MANUAL     => 'Admin (manual grid entry)',
            SuppressionService::SOURCE_LEGACY_IMPORT    => 'Legacy import',
            default                                     => (string) $source,
        };
    }

    public function getAdminUsername(?int $adminUserId): string
    {
        if ($adminUserId === null || $adminUserId <= 0) {
            return '—';
        }
        $user = $this->userFactory->create()->load($adminUserId);
        return $user->getId() ? (string) $user->getUsername() : '(deleted)';
    }
}
