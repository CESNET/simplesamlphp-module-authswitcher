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
        
        if (!is_array($factors) || $factors != array_filter($factors, 'is_int') || min($factors) < 1 || max($factors) > AuthSwitcher::FACTOR_MAX) {
            throw new Exception('Invalid factors passed: '.$factors);
        }
    }
}

class AuthSwitcher {
    const FACTOR_MAX = 2;
    const FACTOR_SECOND = 2;
}

class sspmod_authswitcher_Auth_Process_SwitchAuth extends SimpleSAML_Auth_ProcessingFilter {
    const DEBUG_PREFIX = 'authswitcher:SwitchAuth: ';

    private $modules = array();

    private $methods = array(
        new aswAuthMethod('simpletotp', 'ga_secret', array(AuthSwitcher::FACTOR_SECOND)),
        new aswAuthMethod('authYubiKey', 'yubikey', array(AuthSwitcher::FACTOR_SECOND)),
    );
    
    private function warning($message) {
        SimpleSAML_Logger::warning(self::DEBUG_PREFIX . $message);
    }
    
    public function __construct($info, $config) {
        parent::__construct($info, $config);

        assert(class_exists('DataAdapter'));

        if (is_array($config['modules'])) {
            $validModules = array_filter(array_map(array('Module','isModuleEnabled'), $config['modules']));
            if ($vaildModules !== $config['modules']) {
                $this->warning('Some modules in the configuration are missing or disabled. These modules were skipped.');
            }
        $this->modules = $validModules;
        }
    }
}
