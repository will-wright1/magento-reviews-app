<?php
/**
 * Email 3 - Product review request.
 *
 * Sent after the company review request. It asks the customer to
 * review the products from their order using Magento's native product review
 * form route.
 */
declare(strict_types=1);

namespace ReviewAutomation\Reviews\Model\Email;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Framework\App\ResourceConnection;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;
use ReviewAutomation\Reviews\Model\Config;
use ReviewAutomation\Reviews\Model\Queue;

class ProductReviewRequestEmail
{
    public function __construct(
        private readonly MessageBuilder $messageBuilder,
        private readonly TemplateLoader $templateLoader,
        private readonly Config $config,
        private readonly StoreManagerInterface $storeManager,
        private readonly ResourceConnection $resourceConnection,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly LoggerInterface $logger
    ) {
    }

    public function hasReviewableProducts(Queue $queue): bool
    {
        try {
            if (!$this->config->areMagentoProductReviewsEnabled((int) $queue->getStoreId())) {
                return false;
            }
            return !empty($this->loadReviewableProducts($queue));
        } catch (\Throwable $e) {
            $this->logger->warning(sprintf(
                'ReviewAutomation: Product review sendability check failed for queue_id=%d - %s',
                (int) $queue->getId(),
                $e->getMessage()
            ));
            return false;
        }
    }

    /**
     * Returns false on any failure so the cron caller moves on rather than
     * aborting the whole batch via an uncaught exception.
     */
    public function send(Queue $queue): bool
    {
        $storeId = (int) $queue->getStoreId();
        $firstname = (string) ($queue->getCustomerFirstname() ?: 'there');
        $incrementId = (string) $queue->getOrderIncrementId();
        $email = (string) $queue->getCustomerEmail();
        $token = (string) $queue->getToken();

        try {
            if (!$this->config->areMagentoProductReviewsEnabled($storeId)) {
                return false;
            }

            $products = $this->loadReviewableProducts($queue);
            if (empty($products)) {
                $this->logger->info(sprintf(
                    'ReviewAutomation: ProductReviewRequestEmail skipped for queue_id=%d - no reviewable products.',
                    (int) $queue->getId()
                ));
                return false;
            }

            $store = $this->storeManager->getStore($storeId);
            $baseUrl = rtrim($store->getBaseUrl(), '/') . '/';
            $unsubscribeUrl = $baseUrl . 'reviewautomation_reviews/unsubscribe/index/token/' . $token;

            $templateId = $this->config->getProductReviewRequestEmailTemplate($storeId);
            $result = $this->templateLoader->render($templateId, [
                '__FIRSTNAME__'                   => $this->esc($firstname),
                '__ORDER_INCREMENT_ID__'          => $this->esc($incrementId),
                '__PRODUCT_REVIEW_BUTTONS_HTML__' => $this->renderProductButtonsHtml($products),
                '__UNSUBSCRIBE_URL__'             => $this->esc($unsubscribeUrl),
            ], $storeId);
        } catch (\Throwable $e) {
            $this->logger->error(sprintf(
                'ReviewAutomation: ProductReviewRequestEmail send failed for queue_id=%d, store_id=%d - %s',
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

    /**
     * @return array<int,array{name:string,url:string}>
     */
    private function loadReviewableProducts(Queue $queue): array
    {
        $storeId = (int) $queue->getStoreId();
        $store = $this->storeManager->getStore($storeId);
        $websiteId = (int) $store->getWebsiteId();
        $baseUrl = rtrim($store->getBaseUrl(), '/') . '/';

        $connection = $this->resourceConnection->getConnection();
        $itemTable = $this->resourceConnection->getTableName('sales_order_item');

        $rows = $connection->fetchAll(
            $connection->select()
                ->from($itemTable, ['item_id', 'product_id'])
                ->where('order_id = ?', (int) $queue->getOrderId())
                ->where('parent_item_id IS NULL')
                ->where('product_id IS NOT NULL')
                ->order('item_id ASC')
        );

        $products = [];
        $seen = [];
        foreach ($rows as $row) {
            $productId = (int) ($row['product_id'] ?? 0);
            if ($productId <= 0 || isset($seen[$productId])) {
                continue;
            }
            $seen[$productId] = true;

            try {
                $product = $this->productRepository->getById($productId, false, $storeId);
            } catch (\Throwable $e) {
                $this->logger->warning(sprintf(
                    'ReviewAutomation: Product review link skipped for missing product_id=%d on queue_id=%d.',
                    $productId,
                    (int) $queue->getId()
                ));
                continue;
            }

            if ((int) $product->getStatus() !== Status::STATUS_ENABLED) {
                continue;
            }
            if (!$product->isVisibleInCatalog() || !$product->isVisibleInSiteVisibility()) {
                continue;
            }

            $websiteIds = array_map('intval', $product->getWebsiteIds());
            if (!in_array($websiteId, $websiteIds, true)) {
                continue;
            }

            $products[] = [
                'name' => (string) $product->getName(),
                'url'  => $baseUrl . 'review/product/list/id/' . $productId . '/#review-form',
            ];
        }

        return $products;
    }

    /**
     * @param array<int,array{name:string,url:string}> $products
     */
    private function renderProductButtonsHtml(array $products): string
    {
        $rows = '';
        foreach ($products as $product) {
            $name = $this->esc($product['name']);
            $url = $this->esc($product['url']);
            $rows .= <<<HTML
<tr>
    <td class="order-item" style="padding:16px 0; border-bottom:1px solid #e4e8e7;">
        <div class="order-name" style="font-family:Arial,Helvetica,sans-serif; font-size:15px; font-weight:700; color:#1a1a1a; line-height:1.4; margin:0 0 14px 0; overflow-wrap:anywhere;">{$name}</div>
        <a class="email-button" href="{$url}" target="_blank" rel="noopener" style="display:block; background-color:#243b53; border:1px solid #243b53; color:#ffffff; font-family:Arial,Helvetica,sans-serif; font-size:16px; line-height:22px; font-weight:700; text-decoration:none; padding:16px 20px; border-radius:4px; mso-padding-alt:0; mso-border-alt:16px solid #243b53; text-align:center;">
            Review this product
        </a>
    </td>
</tr>
HTML;
        }

        return $rows;
    }

    private function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
