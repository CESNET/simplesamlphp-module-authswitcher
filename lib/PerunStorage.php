<?php

/**
 * Perun storage for module totp.
 */

namespace SimpleSAML\Module\authswitcher;

use Jose\Component\Core\JWKSet;
use Jose\Easy\Build;

class PerunStorage extends DatabaseStorage
{
    private const CONFIG_FILE = 'module_authswitcher.php';

    public function __construct()
    {
        parent::__construct();
    }

    public function store($userId, $secret, $label = '')
    {
        $token = [
            'type' => 'TOTP',
            'name' => empty($label) ? 'TOTP' : $label,
            'data' => [
                'secret' => $secret,
            ],
        ];

        $config = Configuration::loadFromArray(
            Configuration::getOptionalConfig(self::CONFIG_FILE)->getArray('PerunStorage', [])
        );

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $config->getString('apiURL'));
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        $paramsJson = json_encode($token);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $paramsJson);
        $time = time();
        $jwkset = JWKSet::createFromJson(file_get_contents($config->getString('OIDCKeyStore')));
        $jwk = $jwkset->get($config->getString('OIDCKeyId', 'rsa1'));
        $id_token = Build::jws()
            ->exp($time + $config->getInteger('OIDCTokenTimeout', 300))
            ->iat($time)
            ->nbf($time)
            ->alg($config->getString('OIDCTokenAlg', 'RS256'))
            ->iss($config->getString('OIDCIssuer'))
            ->aud($config->getString('OIDCClientId'))
            ->sub($userId)
            ->claim('acr', 'https://refeds.org/profile/mfa')
            ->sign($jwk);
        curl_setopt(
            $ch,
            CURLOPT_HTTPHEADER,
            [
                'Content-Type: application/json',
                'Content-Length: ' . strlen($paramsJson),
                'Authorization: Bearer ' . $id_token,
            ]
        );
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_exec($ch);
        curl_close($ch);
    }
}
