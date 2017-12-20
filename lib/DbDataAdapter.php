<?php
class sspmod_authswitcher_DbDataAdapter implements sspmod_authswitcher_DataAdapter {
    const DB_FIELD_NAMES = array('dsn', 'user', 'pass');
    const DB_TABLE_SETTING = "auth_method_setting";

    private $dsn;
    private $user;
    private $pass;
    private $dbh;
    private $db_prefix = "miasw_";

    /** Get prefixed table name. */
    private function table(string $name) {
        return $this->db_prefix . constant('self::DB_TABLE_' . $name);
    }

    /** Get a configuration parameter from the config array. */
    private function dbConfig(array $config, string $fieldName) {
        if (isset($config[$fieldName]) && is_string($config[$fieldName])) {
            $this->$fieldName = $config[$fieldName];
        }
    }
    
    /** @override */
    public function __construct(array $config) {
        foreach (self::DB_FIELD_NAMES as $fieldName) {
            $this->dbConfig($config, $fieldName);
        }
        try {
            $this->dbh = new PDO($this->dsn, $this->user, $this->pass);
        } catch (PDOException $e) {
            echo 'Connection failed: '.$e->getMessage();
        }
        $this->createTables();
    }
    
    /* https://yuml.me/edit/22d9ff74 */
    /* https://yuml.me/edit/6c9f38e5 */
    /** Create DB tables. */
    private function createTables() {
	$q = 'CREATE TABLE IF NOT EXISTS '. $this->table('SETTING') . ' (
		uid varchar(30) NOT NULL,
		method varchar(64) NOT NULL,
		priority tinyint(1) UNSIGNED NOT NULL DEFAULT 0,
		factor tinyint(1) UNSIGNED NOT NULL,
		parameter varchar(128) DEFAULT NULL,
		PRIMARY KEY(uid, method, factor, parameter)
		);';
        $this->dbh->exec($q);
    }
    
    /** @override */
    public function getMethodsActiveForUidAndFactor(string $uid, int $factor) {
        $statement = $this->dbh->prepare('SELECT method, parameter FROM '. $this->table('SETTING') . ' WHERE uid = ? AND factor = ? ORDER BY priority ASC');
        $statement->execute(array($uid, $factor));
        return $statement->fetchAll(PDO::FETCH_CLASS, 'sspmod_authswitcher_MethodParams');
    }
}
