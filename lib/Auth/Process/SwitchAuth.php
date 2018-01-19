<?php
require_once __DIR__ . '/../../defaultAuthFilterMethods.php';

class sspmod_authswitcher_Auth_Process_SwitchAuth extends SimpleSAML_Auth_ProcessingFilter {
    /* constants */
    const DEBUG_PREFIX = 'authswitcher:SwitchAuth: ';
    /** If true, then e.g. the absence of methods for 2nd factor mean that 3rd factor won't be tried, even if configured. */
    const FINISH_WHEN_NO_METHODS = true;

    /* configurable attributes */
    /** Associative array where keys are in form 'module:filter' and values are config arrays to be passed to those filters. */
    private $configs = array();
    /** Maximal supported "n" in "n-th factor authentication" */
    private $supportedFactorMax = sspmod_authswitcher_AuthSwitcherFactor::SECOND;
    /** DataAdapter implementation class name */
    private $dataAdapterClassName;

    /** Second constructor parameter */
    private $reserved;
    /** DataAdapter for getting users' settings. */
    private $dataAdapter = null;

    /** Lazy getter for DataAdapter */
    private function getData() {
        if ($this->dataAdapter == null) {
            $className = $this->dataAdapterClassName;
            $this->dataAdapter = new $className();
        }
        return $this->dataAdapter;
    }
    
    /* logging */
    /** Log a warning. */
    private function warning(/*string*/ $message) {
        SimpleSAML_Logger::warning(self::DEBUG_PREFIX . $message);
    }
    /** Log an info. */
    private function info(/*string*/ $message) {
        SimpleSAML_Logger::info(self::DEBUG_PREFIX . $message);
    }

    /** @override */
    public function __construct($config, $reserved) {
        parent::__construct($config, $reserved);

        assert(interface_exists('sspmod_authswitcher_DataAdapter'));

        $this->getConfig($config);

        $this->reserved = $reserved;
    }
    
    /** Get configuration parameters from the config array. */
    private function getConfig(array $config) {
        if (!is_array($config['configs'])) {
            throw new SimpleSAML_Error_Exception(self::DEBUG_PREFIX . 'Configurations are missing.');
        }
        $filterModules = array_keys($config['configs']);
        $invalidModules = sspmod_authswitcher_Utils::areFilterModulesEnabled($filterModules);
        if ($invalidModules !== true) {
            $this->warning('Some modules ('. implode(',', $invalidModules) .') in the configuration are missing or disabled.');
        }
        $this->configs = $config['configs'];
        
        if (isset($config['supportedFactorMax'])) {
            if (!is_int($config['supportedFactorMax']) || $config['supportedFactorMax'] < sspmod_authswitcher_AuthSwitcher::FACTOR_MIN) {
               throw new SimpleSAML_Error_Exception(self::DEBUG_PREFIX . 'Invalid configuration parameter supportedFactorMax.');
            }
            $this->supportedFactorMax = $config['supportedFactorMax'];
        }
        
        if (!is_string($config['dataAdapterClassName'])) {
            throw new SimpleSAML_Error_Exception(self::DEBUG_PREFIX . 'dataAdapterClassName is missing.');
        }
        if (!class_exists($config['dataAdapterClassName'])) {
            throw new SimpleSAML_Error_Exception(self::DEBUG_PREFIX . 'dataAdapterClassName does not exist.');
        }
        if (!in_array('sspmod_authswitcher_DataAdapter', class_implements($config['dataAdapterClassName']))) {
            throw new SimpleSAML_Error_Exception(self::DEBUG_PREFIX . 'dataAdapterClassName does not implement sspmod_authswitcher_DataAdapter');
        }
        $this->dataAdapterClassName = $config['dataAdapterClassName'];
    }

    /** Prepare before running auth proc filter (e.g. add atributes with secret keys) */
    private function prepareBeforeAuthProcFilter(sspmod_authswitcher_MethodParams $method, &$request) {
        list($module, $simpleClass) = explode(":", $method->method);
        $filterMethodClassName = "aswAuthFilterMethod_" . $module . "_" . $simpleClass;
        $filterMethod = new $filterMethodClassName($method);
        $filterMethod->process($request);
    }
    
    /** @override */
    public function process(&$request) {
        $uid = $request['Attributes'][sspmod_authswitcher_AuthSwitcher::UID_ATTR][0];
        $request['Attributes'][sspmod_authswitcher_AuthSwitcher::MFA_PERFORMED_ATTR] = array();
        for ($factor = sspmod_authswitcher_AuthSwitcher::FACTOR_MIN; $factor <= $this->supportedFactorMax; $factor++) {
            $methods = $this->getData()->getMethodsActiveForUidAndFactor($uid, $factor);

            if (count($methods) == 0) {
                $this->logNoMethodsForFactor($uid, $factor);

                if (self::FINISH_WHEN_NO_METHODS) return;
                else continue;
            }

            $method = $this->chooseMethod($methods);
            $methodClass = $method->method;

            if (!isset($this->configs[$methodClass])) {
                throw new SimpleSAML_Error_Exception(self::DEBUG_PREFIX . 'Configuration for ' . $methodClass . ' is missing.');
            }

            $this->prepareBeforeAuthProcFilter($method, $request);

            $request['Attributes'][sspmod_authswitcher_AuthSwitcher::MFA_PERFORMED_ATTR][] = $methodClass;
            sspmod_authswitcher_Utils::runAuthProcFilter($methodClass, $this->configs[$methodClass], $request, $this->reserved);
        }
    }
    
    /** Choose an appropriate method from the set.
     * @todo filter methods based on device (availability)
     */
    private function chooseMethod(array $methods) {
        return $methods[0];
    }
    
    /** Log that a user has no methods for n-th factor. */
    private function logNoMethodsForFactor(/*string*/ $uid, /*int*/ $factor) {
        if ($factor == sspmod_authswitcher_AuthSwitcher::FACTOR_MIN) {
            $this->info('User '.$uid.' has no methods for factor '.$factor.'. MFA not performed at all.');
        } else {
            $this->info('User '.$uid.' has no methods for factor '.$factor);
        }
    }
}

