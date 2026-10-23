<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
use Magento\Framework\App\Config\ScopeConfigInterface;
use ReviewAutomation\Reviews\Model\Config;
use ReviewAutomation\Reviews\Model\Queue;
use ReviewAutomation\Reviews\Model\Email\Schedule;
use ReviewAutomation\Reviews\Model\Email\SendResult;
use ReviewAutomation\Reviews\Model\Email\SendDispatcher;
use ReviewAutomation\Reviews\Model\Email\SendGuard;
use ReviewAutomation\Reviews\Model\Email\SatisfactionEmail;
use ReviewAutomation\Reviews\Model\Email\SatisfactionReminderEmail;
use ReviewAutomation\Reviews\Model\Email\ReviewRequestEmail;
use ReviewAutomation\Reviews\Model\Email\ProductReviewRequestEmail;
use ReviewAutomation\Reviews\Model\ResourceModel\Queue as QueueResource;
function eq($expected, $actual, string $name): void {
    global $tests;
    $tests++;
    if ($expected !== $actual) { throw new RuntimeException($name . ': expected ' . var_export($expected, true) . ', got ' . var_export($actual, true)); }
    echo "PASS $name\n";
}
class Scope implements ScopeConfigInterface {
    public array $values = [];
    public function getValue($path, $scope = null, $store = null) { return $this->values[$store . ':' . $path] ?? $this->values[$path] ?? null; }
    public function isSetFlag($path, $scope = null, $store = null) { return (bool) $this->getValue($path, $scope, $store); }
    public function set(string $path, $value): void { $this->values['reviewautomation_review_automation/' . $path] = $value; }
}
$tests = 0;
$scope = new Scope(); $config = new Config($scope);
eq(false, $config->isEnabled(), 'fresh config cannot send');
eq([], $config->getAllowedStoreIds(), 'empty stores block all');
$scope->set('filters/allowed_store_ids', '0,-1,a,2,2,3');
eq([2,3], $config->getAllowedStoreIds(), 'allowlist normalises and deduplicates');
$scope->values['trans_email/ident_general/name'] = 'Example Store';
$scope->values['trans_email/ident_general/email'] = 'hello@example.com';
eq('Example Store', $config->getBrandName(2), 'brand uses store identity');
eq('hello@example.com', $config->getSenderEmail(2), 'sender uses store identity');
$scope->set('branding/accent_color', 'red;display:none'); eq('#2563eb', $config->getAccentColor(), 'invalid colour rejected');
$scope->set('branding/accent_color', '#aBc123'); eq('#aBc123', $config->getAccentColor(), 'valid colour accepted');
foreach (['javascript:alert(1)', 'http://example.com', 'https://user:pass@example.com'] as $unsafe) {
    $scope->set('platforms/google_url', $unsafe); eq('', $config->getGoogleReviewUrl(), 'unsafe URL rejected: ' . $unsafe);
}
$scope->set('platforms/google_url', 'https://example.com/review'); eq(true, $config->shouldShowGoogle(), 'configured HTTPS platform enabled');
$scope->set('general/email2_delay_hours', 96);
$scope->set('general/email1_reminder_delay_days', 3);
$scope->set('general/email2_nudge_enabled', 1);
$scope->set('general/email2_nudge_delay_days', 5);
$scope->set('general/product_review_delay_days', 14);
$scope->values['catalog/review/active'] = 1;
$schedule = new Schedule($config); $now = strtotime('2026-10-21 12:00:00 UTC');
$q = new Queue(['queue_id'=>1,'store_id'=>2,'status'=>'pending','created_at'=>gmdate('Y-m-d H:i:s',$now-299)]);
eq(null, $schedule->next($q,$now), 'first email respects five-minute buffer');
$q->setData('created_at',gmdate('Y-m-d H:i:s',$now-300)); eq(SendResult::TYPE_EMAIL1,$schedule->next($q,$now),'first email due at boundary');
$q->setData('email1_sent_at',gmdate('Y-m-d H:i:s',$now-3*86400)); $q->setStatus('email1_sent');
eq(SendResult::TYPE_EMAIL1_REMINDER,$schedule->next($q,$now),'non-responder reminder due');
$q->setSentiment('unhappy'); eq(null,$schedule->next($q,$now),'responders do not get satisfaction reminder');
$q->setData('email1_sent_at',gmdate('Y-m-d H:i:s',$now-4*86400));
foreach (['happy','unhappy',null] as $sentiment) {
    $q->setSentiment($sentiment); $q->setStatus($sentiment === 'unhappy' ? 'feedback_received' : 'email1_sent');
    eq(SendResult::TYPE_EMAIL2,$schedule->next($q,$now),'public invitation independent of sentiment: '.($sentiment??'no response'));
}
$q->setStatus('unsubscribed'); eq(null,$schedule->next($q,$now),'unsubscribe prevents scheduled sending');
$q->setStatus('suppressed'); eq(null,$schedule->next($q,$now),'suppression prevents scheduled sending');
$q->setStatus('email2_sent'); $q->setData('email2_sent_at',gmdate('Y-m-d H:i:s',$now-5*86400));
eq(SendResult::TYPE_NUDGE,$schedule->next($q,$now),'single public review nudge due');
$q->setData('platform_click_at',gmdate('Y-m-d H:i:s',$now)); eq(null,$schedule->next($q,$now),'platform visit stops nudge');
$q->setData('email2_sent_at',gmdate('Y-m-d H:i:s',$now-14*86400));
eq(SendResult::TYPE_PRODUCT_REVIEW,$schedule->next($q,$now),'product request uses company email timestamp');
$q->setData('product_review_sent_at',gmdate('Y-m-d H:i:s',$now)); eq(null,$schedule->next($q,$now),'product request only once');
$scope->values['2:reviewautomation_review_automation/general/email2_delay_hours']=120;
$q->setData('email2_sent_at',null); $q->setSentiment('happy');
eq(null,$schedule->next($q,$now),'store-specific delay overrides global');
unset($scope->values['2:reviewautomation_review_automation/general/email2_delay_hours']);
class Mail1 extends SatisfactionEmail { public int $sent=0; public bool $ok=true; public function __construct() {} public function send(Queue $queue): bool { $this->sent++; return $this->ok; } }
class Reminder extends SatisfactionReminderEmail { public function __construct() {} public function send(Queue $queue): bool { return true; } }
class ReviewMail extends ReviewRequestEmail { public function __construct() {} public function send(Queue $queue,bool $isNudge=false): bool { return true; } }
class ProductMail extends ProductReviewRequestEmail { public function __construct() {} public function send(Queue $queue): bool { return true; } public function hasReviewableProducts(Queue $queue): bool { return true; } }
class Resource extends QueueResource {
    public array $row=['queue_id'=>1,'store_id'=>2,'status'=>'pending']; public int $saves=0;
    public function __construct() {} public function load($object,$value,$field=null) { $object->setData($this->row); return $this; }
    public function save($object) { $this->row=$object->getData(); $this->saves++; return $this; }
}
class Guard extends SendGuard { public ?string $blocked=null; public function __construct() {} public function reason(Queue $queue,string $type): ?string { return $this->blocked; } }
class Locks implements Magento\Framework\Lock\LockManagerInterface {
    public bool $available=true; public int $unlocks=0;
    public function lock($name,$timeout=-1) { return $this->available; } public function unlock($name) { $this->unlocks++; return true; } public function isLocked($name) { return !$this->available; }
}
$mail = new Mail1(); $res = new Resource(); $guard = new Guard(); $locks=new Locks();
$dispatcher=new SendDispatcher($mail,new Reminder(),new ReviewMail(),new ProductMail(),$res,new Magento\Framework\Stdlib\DateTime\DateTime(),$locks,$guard);
$q=new Queue(['queue_id'=>1]);
$guard->blocked='Customer suppressed.'; eq(false,$dispatcher->dispatch($q)->isSuccess(),'manual send honours guard'); eq(0,$mail->sent,'blocked recipient receives no mail');
$guard->blocked=null; $locks->available=false; $unlocks=$locks->unlocks;
eq(false,$dispatcher->dispatch($q)->isSuccess(),'competing worker cannot send'); eq($unlocks,$locks->unlocks,'does not unlock another worker lock');
$locks->available=true; $mail->ok=false;
eq(false,$dispatcher->dispatch($q)->isSuccess(),'mail transport failure reported'); eq(0,$res->saves,'failure does not advance queue');
$mail->ok=true; eq(true,$dispatcher->dispatch($q)->isSuccess(),'retry succeeds'); eq('email1_sent',$res->row['status'],'successful send advances queue');
eq(true,isset($res->row['email1_sent_at']),'successful send stamps message time');
$before=$mail->sent; eq(false,$dispatcher->dispatch(new Queue(['queue_id'=>1]),true,SendResult::TYPE_EMAIL1)->isSuccess(),'stale worker cannot repeat sent email'); eq($before,$mail->sent,'duplicate check precedes transport');
$before=$res->saves; eq(true,$dispatcher->dispatch(new Queue(['status'=>'pending']),false)->isSuccess(),'test send works without queue persistence'); eq($before,$res->saves,'test send never updates queue');
$crypto=new class implements Magento\Framework\Encryption\EncryptorInterface { public function encrypt($data) {return base64_encode($data);} public function decrypt($data) {return base64_decode($data,true) ?: '';}};
$timestamp=new ReviewAutomation\Reviews\Model\TimestampValidator($crypto);
eq(false,$timestamp->validateToken($timestamp->generateToken()),'instant feedback submission rejected');
eq(true,$timestamp->validateToken(base64_encode((string)(time()-10))),'human-paced feedback accepted');
eq(false,$timestamp->validateToken(base64_encode((string)(time()-3601))),'expired feedback form rejected');
eq(false,$timestamp->validateToken(base64_encode('not-a-time')),'tampered feedback token rejected');
// Render actual module email files through the actual loader with a test template factory.
$templateFactory = new class extends Magento\Email\Model\TemplateFactory {
    public function create(array $data = []) { return new class {
        private string $body=''; public function setDesignConfig($data) {return $this;}
        public function loadDefault($id) { $map=['reviewautomation_review_satisfaction_check'=>'satisfaction_check','check'=>'satisfaction_check','review'=>'review_request','product'=>'product_review_request']; $this->body=file_get_contents(dirname(__DIR__).'/view/frontend/email/'.$map[$id].'.html'); return $this; }
        public function getTemplateText() {return $this->body;}
        public function getTemplateSubject() {preg_match('/<!--@subject (.*?) @-->/', $this->body,$m);return $m[1]??'';}
    }; }
};
$logger = new class implements Psr\Log\LoggerInterface {public function warning($message,array $context=[]) {}};
$loader=new ReviewAutomation\Reviews\Model\Email\TemplateLoader($templateFactory,$logger,$config);
$replacements=['__FIRSTNAME__'=>'A &amp; B','__ORDER_INCREMENT_ID__'=>'100001','__ORDER_ITEMS_HTML__'=>'<tr><td>Product</td></tr>','__HAPPY_URL__'=>'https://example.com/happy','__UNHAPPY_URL__'=>'https://example.com/feedback','__PUBLIC_REVIEW_URL__'=>'https://example.com/review','__UNSUBSCRIBE_URL__'=>'https://example.com/unsubscribe','__PLATFORM_BUTTONS_HTML__'=>'<tr><td><a style="color:__ACCENT_COLOR__">Review</a></td></tr>','__PRODUCT_REVIEW_BUTTONS_HTML__'=>'<tr><td>Products</td></tr>'];
foreach (['check','review','product'] as $id) {
    $rendered=$loader->render($id,$replacements,2); eq(false,(bool)preg_match('/__[A-Z_]+__/',$rendered['html']),'no unresolved placeholders in '.$id); eq(true,str_contains($rendered['html'],'Example Store'),'store branding rendered in '.$id);
}
$rendered=$loader->render('check',$replacements,2); eq(true,str_contains($rendered['subject'],'A & B'),'subject decoded without HTML entities');
$clock=new class implements Magento\Framework\Stdlib\DateTime\TimezoneInterface {
    public $scope;
    public function scopeDate($scope=null,$date=null,$includeTime=false) { $this->scope=$scope; return new DateTime('2026-10-21 10:00:00'); }
};
$window=new ReviewAutomation\Reviews\Model\SendWindow($config,$clock);
$scope->set('general/send_window_enabled',1); $scope->set('general/send_window_start_hour',8); $scope->set('general/send_window_end_hour',19);
eq(true,$window->isOpen(2),'local sending window permits daytime'); eq(2,$clock->scope,'timezone resolved using the actual store ID');
$scope->set('general/send_window_start_hour',12); eq(false,$window->isOpen(2),'outside store window blocks send');
$scope->set('general/send_window_start_hour',20); $scope->set('general/send_window_end_hour',8); eq(false,$window->isOpen(2),'invalid window fails closed');
// Exercise the real send guard with deterministic order/DB and suppression doubles.
class Suppressions extends ReviewAutomation\Reviews\Model\SuppressionService {
    public bool $blocked=false; public function __construct() {} public function isSuppressed(string $email): bool { return $this->blocked; }
}
class Anchors extends ReviewAutomation\Reviews\Model\OrderAnchorResolver {
    public ?DateTimeImmutable $anchor; public function __construct() { $this->anchor=new DateTimeImmutable('-10 days'); }
    public function resolveAnchorForOrderId(int $orderId): ?DateTimeImmutable { return $this->anchor; }
}
class Query {
    public string $table=''; public function from($table,$cols=null) { $this->table=$table;return $this; }
    public function where($condition,$value=null) {return $this;}
}
class Database {
    public array $order=['store_id'=>2,'state'=>'complete','grand_total'=>100]; public int $refunds=0; public int $campaigns=0;
    public function select() {return new Query();} public function fetchRow($query) {return $this->order;}
    public function fetchOne($query) {return $query->table==='sales_creditmemo' ? $this->refunds : $this->campaigns;}
}
class Connection extends Magento\Framework\App\ResourceConnection {
    public Database $db; public function __construct() {$this->db=new Database();}
    public function getConnection() {return $this->db;} public function getTableName($name) {return $name;}
}
$scope->set('general/enabled',1); $scope->set('general/send_window_start_hour',8); $scope->set('general/send_window_end_hour',19);
$scope->set('general/email1_delay_days',7); $scope->set('suppression/suppress_refunded',1); $scope->set('suppression/max_emails_per_month',1);
$connection=new Connection(); $suppression=new Suppressions(); $anchors=new Anchors();
$realGuard=new SendGuard($config,$suppression,$connection,$anchors,$window);
$q=new Queue(['queue_id'=>1,'order_id'=>100,'store_id'=>2,'status'=>'pending','customer_email'=>'customer@example.com','created_at'=>gmdate('Y-m-d H:i:s')]);
eq(null,$realGuard->reason($q,SendResult::TYPE_EMAIL1),'real guard accepts eligible complete order');
$suppression->blocked=true; eq('Customer suppressed.',$realGuard->reason($q,SendResult::TYPE_EMAIL1),'real guard blocks opted-out customer'); $suppression->blocked=false;
$connection->db->refunds=1; eq('Order refunded.',$realGuard->reason($q,SendResult::TYPE_EMAIL1),'late refund blocks dispatch'); $connection->db->refunds=0;
$connection->db->campaigns=1; eq('Monthly campaign limit reached.',$realGuard->reason($q,SendResult::TYPE_EMAIL1),'monthly cap rechecked at dispatch'); $connection->db->campaigns=0;
$connection->db->order['state']='canceled'; eq('Order is missing or no longer complete.',$realGuard->reason($q,SendResult::TYPE_EMAIL1),'late cancellation blocks dispatch'); $connection->db->order['state']='complete';
$q->setData('created_at',gmdate('Y-m-d H:i:s',time()-61*86400)); eq('Queue token expired.',$realGuard->reason($q,SendResult::TYPE_EMAIL1),'expired campaign cannot send');
$q->setData('created_at',gmdate('Y-m-d H:i:s')); $anchors->anchor=new DateTimeImmutable('-100 days');
eq('Order outside the age window.',$realGuard->reason($q,SendResult::TYPE_EMAIL1),'organic old order blocked');
$q->setData('age_exempt',1); eq(null,$realGuard->reason($q,SendResult::TYPE_EMAIL1),'booster may use historical fulfilment');
$suppression->blocked=true; eq('Customer suppressed.',$realGuard->reason($q,SendResult::TYPE_EMAIL1),'booster cannot bypass suppression');

// Render the real satisfaction sender into a capture transport: preview links must never use customer tokens.
$capture = new class extends ReviewAutomation\Reviews\Model\Email\MessageBuilder {
    public array $message = [];
    public function __construct() {}
    public function send(string $subject, string $htmlBody, string $fromEmail, string $fromName, string $toEmail, string $toName, int $storeId): bool {
        $this->message = [$subject, $htmlBody]; return true;
    }
};
$stores = new class implements Magento\Store\Model\StoreManagerInterface {
    public function getStore($store = null) { return new class { public function getBaseUrl() { return 'https://example.com/'; } }; }
};
$emptyItems = new class extends Magento\Framework\App\ResourceConnection {
    public function getTableName($name) { return $name; }
    public function getConnection() { return new class {
        public function select() { return $this; } public function from($a,$b) { return $this; }
        public function where($a,$b=null) { return $this; } public function fetchAll($q) { return []; }
    }; }
};
$sender = new SatisfactionEmail($capture,$loader,$config,$stores,$emptyItems,$logger);
$testQueue = new Queue(['store_id'=>2,'order_id'=>0,'order_increment_id'=>'TEST-1','customer_firstname'=>'  Alex  ','customer_email'=>'admin@example.com','token'=>str_repeat('a',64),'is_preview'=>true]);
eq(true,$sender->send($testQueue),'test email renders through real sender into capture');
eq(true,str_starts_with($capture->message[0],'[TEST] '),'test subject clearly labelled');
eq(true,str_contains($capture->message[1],'Hi Alex,'),'customer name whitespace trimmed');
eq(false,str_contains($capture->message[1],'/token/'),'test email contains no customer token links');
foreach (ReviewAutomation\Reviews\Model\TestEmailPreview::getUrls('https://example.com/') as $type=>$url) {
    eq(true,str_contains($capture->message[1],$url),'test '.$type.' link uses isolated preview route');
}
eq(true,str_contains($capture->message[1],'sample-only previews'),'test email explains preview behavior');
$testQueue->setData('is_preview',false);
eq(true,$sender->send($testQueue),'customer email still renders');
eq(false,str_starts_with($capture->message[0],'[TEST] '),'customer subject has no test label');
eq(true,str_contains($capture->message[1],'/token/'.str_repeat('a',64)),'customer links retain real token route');
eq(false,str_contains($capture->message[1],'/preview/'),'customer email never uses preview route');
eq(false,ReviewAutomation\Reviews\Model\TestEmailPreview::isValidStep('../../submit'),'unknown preview step rejected');
foreach (ReviewAutomation\Reviews\Model\TestEmailPreview::STEPS as $step) {
    eq(true,ReviewAutomation\Reviews\Model\TestEmailPreview::isValidStep($step),'preview step allowed: '.$step);
}
echo "\n$tests checks passed.\n";
