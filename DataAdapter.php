<?php
public class DataAdapter {
    const DB_FIELD_NAMES = array('db_dsn', 'db_user', 'db_pass');
    /*const DB_TABLE_FACTOR = "factor";*/
    const DB_TABLE_SETTING = "auth_method_setting";

    private $db_dsn;
    private $db_user;
    private $db_pass;
    private $dbh;
    private $db_prefix = "miasw_";

    private function table($name) {
        return $this->db_prefix . constant('self::DB_TABLE_' . $name);
    }

    private function dbConfig($config, $fieldName) {
        $configName = str_replace('_', '.', $fieldName);
        
        if (is_string($config[$configName])) {
            $this->$fieldName = $config[$configName];
        }
    }
    
    public function __construct($config) {
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
    
    public function getMethodsActiveForUidAndFactor($uid, $factor) {
        $statement = $this->dbh->prepare('SELECT method, parameter FROM '. $this->table('SETTING') . ' WHERE uid = ? AND factor = ? ORDER BY priority ASC');
        $statement->execute(array($uid, $factor));
        return $statement->fetchAll(PDO::FETCH_OBJ);
    }
}
