<?php
/* TODO: remove this inclusion */
require_once '../../../DataAdapter.php';

/** Authentication method (module with an auth proc filter) which can be used for n-th factor authentication. */
class aswAuthMethod {
    /** Module (folder) name, such as "authYubiKey" */
    private $moduleName;
    /** Name of the field that the module's auth proc filter requires, such as "yubikey" */
    private $targetFieldName;
    /** Array of integers limiting for which steps (2FA, 3FA, ...) this can be used */
    private $factors;
    
    public function __construct($moduleName, $targetFieldName, $factors) {
        if (!is_string($moduleName) || !Module::isModuleEnabled($moduleName))
            throw new Exception('Invalid module name passed: '.$moduleName);
        $this->moduleName = $moduleName;

        if (!is_string($targetFieldName))
            throw new Exception('Invalid field name passed: '.$targetFieldName);
        $this->targetFieldName = $targetFieldName;
        
        if (!is_array($factors) || $factors != array_filter($factors, 'is_int') || min($factors) < 1) {
            throw new Exception('Invalid factors passed: '.$factors);
        }
    }
}

/** Module-wide constants. */
class AuthSwitcher {
    /** Name of the uid attribute. */
    const UID_ATTR = 'uid';
}

/** Enum expressing "n" in "n-th factor authentication" for added readability. */
class AuthSwitcherFactor {
    const FIRST = 1;
    const SECOND = 2;
    const THIRD = 3;
}

class sspmod_authswitcher_Auth_Process_SwitchAuth extends SimpleSAML_Auth_ProcessingFilter {
    /* constants */
    const DEBUG_PREFIX = 'authswitcher:SwitchAuth: ';
    /** If true, then e.g. the absence of methods for 2nd factor mean that 3rd factor won't be tried, even if configured. */
    const FINISH_WHEN_NO_METHODS = true;

    /* configurable attributes */
    /** Associative array where keys are in form 'module:filter' and values are config arrays to be passed to those filters. */
    private $configs = array();
    /** Minimal supported "n" in "n-th factor authentication" */
    private $supportedFactorMin = 2;
    /** Maximal supported "n" in "n-th factor authentication" */
    private $supportedFactorMax = 2;
    /** DataAdapter configuration */
    private $dataAdapterConfig = array();

    /* preset attributes */
    private $methods = array(
        new aswAuthMethod('simpletotp', 'ga_secret', array(AuthSwitcherFactor::SECOND)),
        new aswAuthMethod('authYubiKey', 'yubikey', array(AuthSwitcherFactor::SECOND)),
    );
    /** Second constructor parameter */
    private $reserved;
    /** DataAdapter for getting users' settings. */
    private $dataAdapter = null;

    /** Lazy getter for DataAdapter */
    private function getData() {
        if ($this->dataAdapter == null) {
            $this->dataAdapter = new DataAdapter($this->dataAdapterConfig);
        }
        return $this->dataAdapter;
    }
    
    /* logging */
    /** Log a warning. */
    private function warning($message) {
        SimpleSAML_Logger::warning(self::DEBUG_PREFIX . $message);
    }
    /** Log an info. */
    private function info($message) {
        SimpleSAML_Logger::info(self::DEBUG_PREFIX . $message);
    }

    /** @override */
    public function __construct($config, $reserved) {
        parent::__construct($config, $reserved);

        assert(class_exists('DataAdapter'));

        if (is_array($config['dataAdapterConfig'])) {
            $this->dataAdapterConfig = $config['dataAdapterConfig'];
        }
        
        if (!is_array($config['configs'])) {
            throw new SimpleSAML_Error_Exception(self::DEBUG_PREFIX . 'Configurations are missing.');
        }
        $filterModules = array_keys($config['configs']);
        if (AuthSwitcherUtils::areFilterModulesEnabled($filterModules)) {
            $this->warning('Some modules in the configuration are missing or disabled.');
        }
        $this->configs = $config['configs'];
        
        $this->reserved = $reserved;
    }
    
    /** @override */
    public function process(&$request) {
        $uid = $request['Attributes'][AuthSwitcher::UID_ATTR];
        for ($factor = $this->supportedFactorMin; $factor <= $this->supportedFactorMax; $factor++) {
            $methods = $this->getData()->getMethodsActiveForUidAndFactor($uid, $factor);

            if (count($methods) == 0) {
                if ($factor == $this->supportedFactorMin) {
                    $this->info('User '.$uid.' has no methods for factor '.$factor.'. MFA not performed at all.');
                } else {
                    $this->info('User '.$uid.' has no methods for factor '.$factor);
                }

                if (self::FINISH_WHEN_NO_METHODS) break;
                else continue;
            }

            $method = $this->chooseMethod($methods);

            if (!isset($this->configs[$method])) {
                throw new SimpleSAML_Error_Exception(self::DEBUG_PREFIX . 'Configuration for '.$method.' is missing.');
            }

            AuthSwitcherUtils::runAuthProcFilter($method, $this->configs[$method], $reserved);
        }
    }
    
    /** Choose an appropriate method from the set.
     * @todo filter methods based on device (availability)
     */
    private function chooseMethod($methods) {
        return $methods[0];
    }
}

/** Methods not specific to this module. */
class AuthSwitcherUtils {
    /** Execute an auth proc filter.
     * @see https://github.com/CESNET/perun-simplesamlphp-module/blob/master/lib/Auth/Process/ProxyFilter.php */
    public static function runAuthProcFilter($nestedClass, $config, $reserved) {
        list($module, $simpleClass) = explode(":", $nestedClass);
        $className = 'sspmod_'.$module.'_Auth_Process_'.$simpleClass;
        $authFilter = new $className($config, $reserved);
        $authFilter->process($request);
    }
    
    /** Check if all modules for the specified filters are installed and enabled. */
    public static function areFilterModulesEnabled($filters) {
        foreach ($filters as $filter) {
            list($module) = explode(":", $filter);
            if (!SimpleSAML_Module::isModuleEnabled($module)) return false;
        }
        return true;
    }
}
