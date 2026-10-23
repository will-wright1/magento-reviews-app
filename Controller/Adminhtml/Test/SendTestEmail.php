<?php
/**
 * Test endpoint for the "Send Test Email" config button.
 *
 * Builds an in-memory Queue row populated from the latest eligible order
 * (or mock data if none exists), overrides customer_email to the logged-in
 * admin's address, and runs it through SendDispatcher with persist=false
 * so no actual queue row is touched.
 *
 * Returns a JSON envelope the frontend block renders inline. Errors
 * surface in the message field rather than in var/log — that's the
 * whole point of this tool.
 */
declare(strict_types=1);

namespace ReviewAutomation\Reviews\Controller\Adminhtml\Test;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\Auth\Session as AuthSession;
use Magento\Framework\Controller\Result\JsonFactory;
use ReviewAutomation\Reviews\Model\Email\SendDispatcher;
use ReviewAutomation\Reviews\Model\OrderEligibilityQuery;
use ReviewAutomation\Reviews\Model\Queue;
use ReviewAutomation\Reviews\Model\QueueFactory;

class SendTestEmail extends Action implements \Magento\Framework\App\Action\HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'ReviewAutomation_Reviews::config';

    public function __construct(
        Context $context,
        private readonly JsonFactory $jsonFactory,
        private readonly AuthSession $authSession,
        private readonly QueueFactory $queueFactory,
        private readonly OrderEligibilityQuery $orderEligibilityQuery,
        private readonly SendDispatcher $sendDispatcher,
        private readonly \Magento\Store\Model\StoreManagerInterface $stores
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $result = $this->jsonFactory->create();

        try {
            $adminUser = $this->authSession->getUser();
            if ($adminUser === null) {
                return $result->setData([
                    'success' => false,
                    'message' => 'No admin session — cannot determine recipient.',
                ]);
            }

            $adminEmail     = (string) $adminUser->getEmail();
            $adminFirstname = trim((string) $adminUser->getFirstname()) ?: 'Admin';
            if ($adminEmail === '') {
                return $result->setData([
                    'success' => false,
                    'message' => 'Logged-in admin has no email address on their user profile.',
                ]);
            }

            // Try the latest eligible order; fall back to mock data if none.
            $eligibleOrder = $this->orderEligibilityQuery->findLatestEligibleOrder();
            $usedMockData  = $eligibleOrder === null;

            /** @var Queue $queue */
            $queue = $this->queueFactory->create();
            if ($usedMockData) {
                $queue->setData([
                    'order_id'           => 0,
                    'order_increment_id' => 'TEST-MOCK-' . date('Ymd-His'),
                    'customer_email'     => $adminEmail,             // recipient = admin
                    'customer_firstname' => $adminFirstname,
                    'store_id'           => (int) $this->stores->getDefaultStoreView()->getId(),
                    'token'              => $this->generateMockToken(),
                    'status'             => Queue::STATUS_PENDING,
                ]);
            } else {
                $queue->setData([
                    'order_id'           => (int) $eligibleOrder['entity_id'],
                    'order_increment_id' => (string) $eligibleOrder['increment_id'],
                    'customer_email'     => $adminEmail,             // ← override to admin (NOT real customer)
                    'customer_firstname' => $adminFirstname,
                    'store_id'           => (int) $eligibleOrder['store_id'],
                    'token'              => $this->generateMockToken(),
                    'status'             => Queue::STATUS_PENDING,
                ]);
            }

            $queue->setData('is_preview', true);

            $sendResult = $this->sendDispatcher->dispatch($queue, false);  // persist=false: no DB write

            if ($sendResult->isSuccess()) {
                $msg = $usedMockData
                    ? sprintf(
                        'Test email sent to %s using mock data (no eligible orders found in current filter window).',
                        $adminEmail
                    )
                    : sprintf(
                        'Test email sent to %s using order #%s.',
                        $adminEmail,
                        (string) $eligibleOrder['increment_id']
                    );
                return $result->setData([
                    'success'      => true,
                    'message'      => $msg . ' Links open sample-only preview pages; no customer feedback or email preferences are changed.',
                    'usedMockData' => $usedMockData,
                ]);
            }

            return $result->setData([
                'success'      => false,
                'message'      => $sendResult->getErrorMessage() ?? 'Send failed (no error message).',
                'usedMockData' => $usedMockData,
            ]);
        } catch (\Throwable $e) {
            return $result->setData([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Internal random identifier only. Test email links use the separate,
     * sample-only preview route and never resolve this as a customer token.
     */
    private function generateMockToken(): string
    {
        return bin2hex(random_bytes(32));
    }

}
