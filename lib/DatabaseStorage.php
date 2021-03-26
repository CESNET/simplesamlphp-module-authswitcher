<?php

/**
 * Database storage for module totp.
 */

namespace SimpleSAML\Module\authswitcher;

use SimpleSAML\Configuration;
use SimpleSAML\Database;
use SimpleSAML\Module\totp\Storage;

class DatabaseStorage implements Storage
{
    protected const CONFIG_FILE = 'module_authswitcher.php';

    protected $db;

    public function __construct()
    {
        $this->db = Database::getInstance(
            Configuration::getOptionalConfig(self::CONFIG_FILE)->getConfigItem('store', [])
        );
    }

    public function store($userId, $secret, $label = '')
    {
        $this->db->write(
            'INSERT INTO AttributeFromSQLUnique (uid,attribute,value) '
            . 'VALUES (:uid,:attribute,:value)',
            ['uid' => $userId, 'attribute' => 'totp_secret', 'value' => $secret]
        );
    }
}
