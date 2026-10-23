<?php
/** Minimal test doubles for isolated unit tests; these do not emulate a Magento installation. */
declare(strict_types=1);
namespace Magento\Framework\App\Config {
    interface ScopeConfigInterface { public function getValue($path, $scope = null, $store = null); public function isSetFlag($path, $scope = null, $store = null); }
}
namespace Magento\Sales\Model { class Order { public const STATE_COMPLETE = 'complete'; } }
namespace Magento\Store\Model { interface StoreManagerInterface { public function getStore($store = null); } interface ScopeInterface { public const SCOPE_STORE = 'store'; } }
namespace Magento\Framework\Data { interface OptionSourceInterface { public function toOptionArray(); } }
namespace Magento\Framework\Model {
    class AbstractModel {
        protected array $data = [];
        public function __construct(array $data = []) { $this->data = $data; }
        public function getData($key = null) { return $key === null ? $this->data : ($this->data[$key] ?? null); }
        public function setData($key, $value = null) { if (is_array($key)) { $this->data = $key; } else { $this->data[$key] = $value; } return $this; }
        public function getId() { return $this->getData('queue_id'); }
        public function __call($method, $args) {
            $key = strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', substr($method, 3)));
            return str_starts_with($method, 'get') ? $this->getData($key) : $this->setData($key, $args[0]);
        }
    }
}
namespace Magento\Framework\Model\ResourceModel\Db { class AbstractDb {} }
namespace Magento\Framework\Lock { interface LockManagerInterface { public function lock($name, $timeout = -1); public function unlock($name); public function isLocked($name); } }
namespace Magento\Framework\Stdlib\DateTime { class DateTime { public function gmtDate($format = 'Y-m-d H:i:s', $input = null) { return gmdate($format, $input ?? time()); } } interface TimezoneInterface { public function scopeDate($scope = null, $date = null, $includeTime = false); } }
namespace Magento\Framework\Encryption { interface EncryptorInterface { public function encrypt($data); public function decrypt($data); } }
namespace Magento\Framework\App { class ResourceConnection {} class Area { public const AREA_FRONTEND = 'frontend'; } }
namespace Magento\Email\Model { class TemplateFactory { public function create(array $data = []) {} } }
namespace Psr\Log { interface LoggerInterface { public function warning($message, array $context = []); } }
namespace {
    spl_autoload_register(static function(string $class): void {
        $prefix = 'ReviewAutomation\\Reviews\\';
        if (str_starts_with($class, $prefix)) {
            $file = dirname(__DIR__) . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
            if (is_file($file)) { require $file; }
        }
    });
}
