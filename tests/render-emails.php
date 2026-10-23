<?php
/** Render real templates and real row fragments without a transport or Magento database. */
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

use ReviewAutomation\Reviews\Model\Email\{TemplateLoader, SatisfactionEmail, SatisfactionReminderEmail, ReviewRequestEmail, ProductReviewRequestEmail};

$scope = new class implements Magento\Framework\App\Config\ScopeConfigInterface {
    public string $logo = '';
    public function getValue($path, $scope = null, $store = null) {
        return match ($path) {
            'reviewautomation_review_automation/branding/name' => 'Example Store',
            'reviewautomation_review_automation/branding/logo_url' => $this->logo,
            'reviewautomation_review_automation/branding/accent_color' => '#b59b78',
            default => null,
        };
    }
    public function isSetFlag($path, $scope = null, $store = null) { return false; }
};
$factory = new class extends Magento\Email\Model\TemplateFactory {
    public function create(array $data = []) { return new class {
        private string $body = '';
        public function setDesignConfig($data) { return $this; }
        public function loadDefault($id) {
            $this->body = file_get_contents(dirname(__DIR__) . '/view/frontend/email/' . $id . '.html');
            return $this;
        }
        public function getTemplateText() { return $this->body; }
        public function getTemplateSubject() {
            preg_match('/<!--@subject (.*?) @-->/', $this->body, $matches);
            return $matches[1] ?? '';
        }
    }; }
};
$logger = new class implements Psr\Log\LoggerInterface { public function warning($message, array $context = []) {} };
$loader = new TemplateLoader($factory, $logger, new ReviewAutomation\Reviews\Model\Config($scope));
$resource = new class extends Magento\Framework\App\ResourceConnection {
    public array $rows = [];
    public function getTableName($name) { return $name; }
    public function getConnection() { return new class($this->rows) {
        public function __construct(private array $rows) {}
        public function select() { return $this; }
        public function from($table, $columns) { return $this; }
        public function where($condition, $value = null) { return $this; }
        public function fetchAll($query) { return $this->rows; }
    }; }
};
// These methods deliberately return table rows for compatibility with saved custom templates.
function fragment(string $class, string $method, array $args, ?object $resource = null): string {
    $reflection = new ReflectionClass($class);
    $sender = $reflection->newInstanceWithoutConstructor();
    if ($resource !== null) {
        $reflection->getProperty('resourceConnection')->setValue($sender, $resource);
    }
    $renderer = $reflection->getMethod($method);
    return $renderer->invokeArgs($sender, $args);
}
$base = [
    '__FIRSTNAME__' => 'Alex &amp; Sam',
    '__ORDER_INCREMENT_ID__' => 'TEST-100001',
    '__HAPPY_URL__' => 'https://example.com/feedback?response=happy&amp;token=example',
    '__UNHAPPY_URL__' => 'https://example.com/feedback?response=unhappy&amp;token=example',
    '__PUBLIC_REVIEW_URL__' => 'https://example.com/review',
    '__UNSUBSCRIBE_URL__' => 'https://example.com/unsubscribe',
];
$products = [
    ['name' => 'Traditional White Ceramic Cistern Lever & Chrome Fittings — Long Product Name', 'url' => 'https://example.com/product/review?id=1&token=example'],
    ['name' => '<script>not markup</script> & "quoted" product', 'url' => 'https://example.com/product/review?id=2'],
];
$rendered = [];
foreach (['populated', 'empty', 'logo', 'preview'] as $variant) {
    $resource->rows = $variant === 'empty' ? [] : [
        ['name' => $products[0]['name'], 'qty_ordered' => 1],
        ['name' => $products[1]['name'], 'qty_ordered' => 2],
    ];
    if ($variant === 'preview') { $resource->rows = array_slice($resource->rows, 0, 1); }
    $scope->logo = $variant === 'logo' ? 'https://example.com/logo.png?width=440&height=80' : '';
    $values = $base + [
        '__PLATFORM_BUTTONS_HTML__' => fragment(ReviewRequestEmail::class, 'renderPlatformButton', ['https://example.com/review?token=example&platform=google', 'Review us on Google']),
        '__PRODUCT_REVIEW_BUTTONS_HTML__' => fragment(ProductReviewRequestEmail::class, 'renderProductButtonsHtml', [$variant === 'empty' ? [] : ($variant === 'preview' ? array_slice($products, 0, 1) : $products)]),
    ];
    foreach (['satisfaction_check', 'satisfaction_reminder', 'review_request', 'review_request_nudge', 'product_review_request'] as $id) {
        $values['__ORDER_ITEMS_HTML__'] = fragment(
            $id === 'satisfaction_reminder' ? SatisfactionReminderEmail::class : SatisfactionEmail::class,
            'renderOrderItemsHtml', [123], $resource
        );
        $rendered[$id . '-' . $variant] = $loader->render($id, $values, 1);
    }
}
echo json_encode($rendered, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
