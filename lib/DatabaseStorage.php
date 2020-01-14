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
    private const CONFIG_FILE = 'module_authswitcher.php';

    public function store($userId, $secret, $label = '')
    {
        $db = Database::getInstance(Configuration::getOptionalConfig(self::CONFIG_FILE)->getConfigItem('store', []));
        $db->write(
            'INSERT INTO AttributeFromSQL (uid,attribute,value) '
            . 'VALUES (:uid,:attribute,:value)',
            ['uid' => $userId, 'attribute' => 'totp_secret', 'value' => $secret]
        );
        $db->write(
            'INSERT INTO auth_method_setting (userid,method,tag) '
            . 'VALUES (:uid,:method,:tag)',
            ['uid' => $userId, 'method' => 'totp:Totp', 'tag' => $label]
        );
    }
}
