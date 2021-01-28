<?php

/**
 * Perun storage for module totp.
 */

namespace SimpleSAML\Module\authswitcher;

use Jose\Component\Core\JWKSet;
use Jose\Easy\Build;

class PerunStorage extends DatabaseStorage
{
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

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://id.muni.cz/mfaapi/token');
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        $paramsJson = json_encode($token);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $paramsJson);
        $time = time();
        $jwkset = JWKSet::createFromJson(file_get_contents('/var/oidc-keystore.jwks'));
        $jwk = $jwkset->get('rsa1');
        $id_token = Build::jws()
            ->exp($time + 300)
            ->iat($time)
            ->nbf($time)
            ->alg('RS256')
            ->iss('https://oidc.muni.cz/oidc/')
            ->aud('d574aeba-b2d0-4234-bcf0-53ec30b17ba4')
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

        $this->savePreference($userId, $label);
    }
}
