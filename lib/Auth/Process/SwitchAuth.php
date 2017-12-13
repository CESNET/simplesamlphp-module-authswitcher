<?php
class aswAuthMethod {
    /** Module (folder) name, such as "authYubiKey" */
    private $moduleName;
    /** Name of the field that the module's auth proc filter requires, such as "yubikey" */
    private $targetFieldName;
    /** Array of integers limiting for which steps (2FA, 3FA, ...) this can be used */
    private $factors;
    
    public function __construct($moduleName, $targetFieldName, $factors) {
        if (!is_string($moduleName) || !Module::isModuleEnabled($moduleName))
            throw new Exception("Invalid module name passed: ".$moduleName);
        $this->moduleName = $moduleName;

        if (!is_string($targetFieldName))
            throw new Exception("Invalid field name passed: ".$targetFieldName);
        $this->targetFieldName = $targetFieldName;
        
        if (!is_array($factors) || $factors != array_filter($factors, 'is_int') || min($factors) < 1 || max($factors) > sspmod_authswitcher_Auth_Source_SwitchAuth::FACTOR_MAX) {
            throw new Exception("Invalid factors passed: ".$factors);
        }
    }
}

class sspmod_authswitcher_Auth_Process_SwitchAuth extends SimpleSAML_Auth_ProcessingFilter {
    private $modules = array();
    private $debug = E_USER_NOTICE;
    const DEBUG_CONSTANTS = array(0, E_USER_ERROR, E_USER_WARNING, E_USER_NOTICE);
    private $db_dsn;
    private $db_user;
    private $db_pass;
    const DB_FIELD_NAMES = array('db_dsn', 'db_user', 'db_pass');
    private $dbh;
    private $db_prefix = "miasw_";
    const FACTOR_MAX = 2;

    /*const DB_TABLE_FACTOR = "factor";*/
    const DB_TABLE_SETTING = "auth_method_setting";

    private $methods = array(
        new aswAuthMethod('simpletotp', 'ga_secret', array(2)),
        new aswAuthMethod('authYubiKey', 'yubikey', array(2)),
    );
    
    private function table($name) {
        return $this->db_prefix . constant('self::DB_TABLE_' . $name);
    }

    private function debug($message) {
        if ($debug > 0) {
            trigger_error($message, $debug);
        }
    }
    
    private function dbConfig($config, $fieldName) {
        $configName = str_replace('_', '.', $fieldName);
        
        if (is_string($config[$configName])) {
            $this->$fieldName = $config[$configName];
        }
    }
    
    public function __construct($info, $config) {
        parent::__construct($info, $config);
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
        
        foreach (self::DB_FIELD_NAMES as $fieldName) {
            $this->dbConfig($config, $fieldName);
        }

	try {
            $this->dbh = new PDO($this->db_dsn, $this->db_user, $this->db_pass);
        } catch (PDOException $e) {
            echo 'Connection failed: '.$e->getMessage();
        }
        $this->createTables();
    }
    
    /** https://yuml.me/edit/22d9ff74 */
    /** https://yuml.me/edit/6c9f38e5 */
    private function createTables() {
	/*$q = 'CREATE TABLE IF NOT EXISTS '. $this->table('FACTOR') . ' (
		  factor tinyint(1) UNSIGNED NOT NULL,
		  PRIMARY KEY(factor)
		 );';
        $this->dbh->query($q);

	$q = 'INSERT IGNORE INTO ' . $this->table('FACTOR') . ' VALUES (1)';
	for ($f = 1; $f <= self::FACTOR_MAX; $f++) {
            $q .= '('.$f.')');
        }
	$this->dbh->query($q);
	$this->dbh->query('DELETE FROM ' . $this->table('FACTOR') . ' WHERE factor < 1 OR factor > ' . self::FACTOR_MAX);*/

	$q = 'CREATE TABLE IF NOT EXISTS '. $this->table('SETTING') . ' (
		uid varchar(30) NOT NULL,
		method varchar(64) NOT NULL,
		priority tinyint(1) UNSIGNED NOT NULL DEFAULT 0,
		factor tinyint(1) UNSIGNED NOT NULL,
		parameter varchar(255) DEFAULT NULL,
		PRIMARY KEY(uid, method, factor, parameter)
		);';
        $this->dbh->query($q);
    }
    
    protected function login($username, $password) {
        if ($username !== 'theusername' || $password !== 'thepassword') {
            throw new SimpleSAML_Error_Error('WRONGUSERPASS');
        }
        return array(
            'uid' => array('theusername'),
            'displayName' => array('Some Random User'),
            'eduPersonAffiliation' => array('member', 'employee'),
        );
    }
}
