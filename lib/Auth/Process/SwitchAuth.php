<?php
/* TODO: remove this inclusion */
require_once '../../../DataAdapter.php';

/** Concrete subclasses will be named aswAuthFilterMethod_modulename_filtername */
abstract class aswAuthFilterMethod {
    abstract public function process(&$request);
    abstract public function __construct($methodParams);
}

/** Abstract class for authentication methods which only require a single secret string in an attribute. */
abstract class aswAuthFilterMethodWichSimpleSecret extends aswAuthFilterMethod {
    private $parameter;

    public function __construct($methodParams) {
        $this->parameter = $methodParams['parameter'];
    }
    
    /** @override */
    public function process(&$request) {
        $request['Attributes'][getTargetFieldName()] = $this->parameter;
    }
    
    abstract public function getTargetFieldName();
}

/** Definition for filter yubikey:OTP */
class aswAuthFilterMethod_yubikey_OTP extends aswAuthFilterMethodWichSimpleSecret {
    public function getTargetFieldName() {
        return 'yubikey';
    }
}

/** Definition for filter simpletotp:2fa */
class aswAuthFilterMethod_simpletotp_2fa extends aswAuthFilterMethodWichSimpleSecret {
    public function getTargetFieldName() {
        return 'ga_secret';
    }
}

/** Definition for filter authTiqr:Tiqr */
class aswAuthFilterMethod_authTiqr_Tiqr extends aswAuthFilterMethod {
    /** @override */
    public function process(&$request) {
    }
    
    /** @override */
    public function __construct($methodParams) {
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
