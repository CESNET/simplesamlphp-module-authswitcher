<?php
class sspmod_authswitcher_DbDataAdapter implements sspmod_authswitcher_DataAdapter {
    const DB_FIELD_NAMES = array('dsn', 'user', 'pass');
    /*const DB_TABLE_FACTOR = "factor";*/
    const DB_TABLE_SETTING = "auth_method_setting";

    private $dsn;
    private $user;
    private $pass;
    private $dbh;
    private $db_prefix = "miasw_";

    private function table($name) {
        return $this->db_prefix . constant('self::DB_TABLE_' . $name);
    }

    private function dbConfig($config, $fieldName) {
        if (isset($config[$fieldName]) && is_string($config[$fieldName])) {
            $this->$fieldName = $config[$fieldName];
        }
    }
    
    public function __construct($config) {
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
		parameter varchar(128) DEFAULT NULL,
		PRIMARY KEY(uid, method, factor, parameter)
		);';
        $this->dbh->exec($q);
    }
    
    public function getMethodsActiveForUidAndFactor($uid, $factor) {
        $statement = $this->dbh->prepare('SELECT method, parameter FROM '. $this->table('SETTING') . ' WHERE uid = ? AND factor = ? ORDER BY priority ASC');
        $statement->execute(array($uid, $factor));
        return $statement->fetchAll(PDO::FETCH_OBJ);
    }
}
