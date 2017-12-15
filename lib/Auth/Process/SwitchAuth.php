<?php
/** TO DO remove this inclusion */
require_once '../../../DataAdapter.php';

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
        
        if (!is_array($factors) || $factors != array_filter($factors, 'is_int') || min($factors) < 1 || max($factors) > sspmod_authswitcher_Auth_Source_SwitchAuth::FACTOR_MAX) {
            throw new Exception('Invalid factors passed: '.$factors);
        }
    }
}

class sspmod_authswitcher_Auth_Process_SwitchAuth extends SimpleSAML_Auth_ProcessingFilter {
    const DEBUG_CONSTANTS = array(0, E_USER_ERROR, E_USER_WARNING, E_USER_NOTICE);
    const FACTOR_MAX = 2;
    const FACTOR_SECOND = 2;

    private $modules = array();
    private $debug = E_USER_NOTICE;

    private $methods = array(
        new aswAuthMethod('simpletotp', 'ga_secret', array(FACTOR_SECOND)),
        new aswAuthMethod('authYubiKey', 'yubikey', array(FACTOR_SECOND)),
    );
    
    private function debug($message) {
        if ($debug > 0) {
            trigger_error($message, $debug);
        }
    }
    
    public function __construct($info, $config) {
        parent::__construct($info, $config);

        assert(class_exists('DataAdapter'));

        if (is_array($config['modules'])) {
            $validModules = array_filter(array_map(array('Module','isModuleEnabled'), $config['modules']));
            if ($vaildModules !== $config['modules']) {
                $this->debug('Some modules in authswitcher configuration are missing or disabled. These modules were skipped.');
            }
        $this->modules = $validModules;
        }

        if (in_array($config['debug'], self::DEBUG_CONSTANTS)) {
            $this->debug = $config['debug'];
        }        
    }
}
