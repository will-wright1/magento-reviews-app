<?php
/** Render actual landing templates with sample-only framework/view doubles. */
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
function __($text, ...$args): string { foreach($args as $i=>$arg){$text=str_replace('%'.($i+1),(string)$arg,$text);}return $text; }
class LandingFixture {
    public function __construct(private string $step='happy') {}
    public function getRequest() { return new class($this->step) {
        public function __construct(private string $step) {}
        public function getParam($name,$default=null) {return match($name){'step'=>$this->step,'sentiment'=>'happy','token'=>str_repeat('a',64),default=>$default};}
    }; }
    public function getData($key) {return new class {
        public function getColor(){return '#b59b78';}
        public function getPreviewPlatformLabels(){return ['Review us on Google','Review us on Trustpilot'];}
    };}
    public function escapeHtml($text){return htmlspecialchars((string)$text,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
    public function escapeHtmlAttr($text){return $this->escapeHtml($text);}
    public function escapeUrl($text){return $this->escapeHtml($text);}
    public function getBaseUrl(){return 'https://example.com/';}
    public function getUrl($path,array $params=[]){$url='https://example.com/'.trim($path,'/').'/';foreach($params as $k=>$v){$url.=rawurlencode($k).'/'.rawurlencode((string)$v).'/';}return $url;}
    public function getBlockHtml($name){return '<input type="hidden" name="form_key" value="sample-form-key">';}
    public function getCustomerFirstname(){return 'Alex';}
    public function getOrderIncrementId(){return 'TEST-100001';}
    public function getToken(){return str_repeat('a',64);}
    public function getTimestampToken(){return 'sample-timestamp';}
    public function getFormAction(){return $this->getUrl('reviewautomation_reviews/response/feedbackpost');}
    public function shouldShowGoogle(){return true;}
    public function shouldShowTrustpilot(){return true;}
    public function getGoogleCtaUrl(){return 'https://example.com/google';}
    public function getTrustpilotCtaUrl(){return 'https://example.com/trustpilot';}
}
function renderLanding(string $template,string $step='happy'):string {
    $block=new LandingFixture($step);ob_start();include dirname(__DIR__).'/view/frontend/templates/'.$template.'.phtml';return ob_get_clean();
}
$pages=[];
foreach(['expired','confirm','feedback','thankyou','feedbackthankyou','unsubscribe'] as $name){$pages[$name]=renderLanding($name);}
foreach(ReviewAutomation\Reviews\Model\TestEmailPreview::STEPS as $step){$pages['preview-'.$step]=renderLanding('preview',$step);}
echo json_encode($pages,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES);
