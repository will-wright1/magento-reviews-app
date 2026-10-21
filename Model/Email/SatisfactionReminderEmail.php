<?php
/**
 * Email 1 reminder.
 *
 * Sent once after Email 1 when the customer has not clicked happy or unhappy.
 * Uses the same one-click response URLs as the first satisfaction check.
 */
declare(strict_types=1);

namespace ReviewAutomation\Reviews\Model\Email;

use Magento\Framework\App\ResourceConnection;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;
use ReviewAutomation\Reviews\Model\Config;
use ReviewAutomation\Reviews\Model\Queue;

class SatisfactionReminderEmail
{
    public function __construct(
        private readonly MessageBuilder $messageBuilder,
        private readonly TemplateLoader $templateLoader,
        private readonly Config $config,
        private readonly StoreManagerInterface $storeManager,
        private readonly ResourceConnection $resourceConnection,
        private readonly LoggerInterface $logger
    ) {
    }

    public function send(Queue $queue): bool
    {
        $storeId = (int) $queue->getStoreId();
        $firstname = (string) ($queue->getCustomerFirstname() ?: 'there');
        $incrementId = (string) $queue->getOrderIncrementId();
        $token = (string) $queue->getToken();
        $email = (string) $queue->getCustomerEmail();

        try {
            $store = $this->storeManager->getStore($storeId);
            $baseUrl = rtrim($store->getBaseUrl(), '/') . '/';

            $happyUrl = $baseUrl . 'reviewautomation_reviews/response/click/token/' . $token . '/sentiment/happy';
            $unhappyUrl = $baseUrl . 'reviewautomation_reviews/response/click/token/' . $token . '/sentiment/unhappy';
            $unsubscribeUrl = $baseUrl . 'reviewautomation_reviews/unsubscribe/index/token/' . $token;

            $itemsHtml = $this->renderOrderItemsHtml((int) $queue->getOrderId());

            $templateId = $this->config->getSatisfactionReminderEmailTemplate($storeId);
            $result = $this->templateLoader->render($templateId, [
                '__FIRSTNAME__'          => $this->esc($firstname),
                '__ORDER_INCREMENT_ID__' => $this->esc($incrementId),
                '__HAPPY_URL__'          => $this->esc($happyUrl),
                '__UNHAPPY_URL__'        => $this->esc($unhappyUrl),
                '__ORDER_ITEMS_HTML__'   => $itemsHtml,
                '__PUBLIC_REVIEW_URL__' => $this->esc($baseUrl . 'reviewautomation_reviews/response/thankyou/token/' . $token),
                '__UNSUBSCRIBE_URL__'    => $this->esc($unsubscribeUrl),
            ], $storeId);
        } catch (\Throwable $e) {
            $this->logger->error(sprintf(
                'ReviewAutomation: SatisfactionReminderEmail send failed for queue_id=%d, store_id=%d - %s',
                (int) $queue->getId(),
                $storeId,
                $e->getMessage()
            ));
            return false;
        }

        return $this->messageBuilder->send(
            $result['subject'],
            $result['html'],
            $this->config->getSenderEmail($storeId),
            $this->config->getSenderName($storeId),
            $email,
            $firstname,
            $storeId
        );
    }

    private function renderOrderItemsHtml(int $orderId): string
    {
        $connection = $this->resourceConnection->getConnection();
        $itemTable = $this->resourceConnection->getTableName('sales_order_item');

        $select = $connection->select()
            ->from($itemTable, ['name', 'qty_ordered'])
            ->where('order_id = ?', $orderId)
            ->where('parent_item_id IS NULL');

        $rows = $connection->fetchAll($select);
        if (empty($rows)) {
            return '';
        }

        $rowsHtml = '';
        foreach ($rows as $row) {
            $name = $this->esc((string) $row['name']);
            $qty = (int) $row['qty_ordered'];
            $rowsHtml .= <<<HTML
<tr>
    <td style="padding:10px 12px; border-bottom:1px solid #f0f0f0; font-family:Arial,Helvetica,sans-serif; font-size:14px; color:#333333;">{$name}</td>
    <td style="padding:10px 12px; border-bottom:1px solid #f0f0f0; font-family:Arial,Helvetica,sans-serif; font-size:14px; color:#888888; text-align:right; white-space:nowrap;">Qty: {$qty}</td>
</tr>
HTML;
        }

        return <<<HTML
<tr>
    <td style="padding:8px 32px 16px 32px;">
        <div style="font-family:Arial,Helvetica,sans-serif; font-size:13px; color:#888888; margin-bottom:6px;">Your order:</div>
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border:1px solid #f0f0f0; border-radius:4px;">
            {$rowsHtml}
        </table>
    </td>
</tr>
HTML;
    }

    private function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
