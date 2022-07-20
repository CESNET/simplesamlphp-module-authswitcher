<?php

declare(strict_types=1);

$config = [
    'type_filter_array' => [
        'TOTP' => 'privacyidea:PrivacyideaAuthProc',
        'WebAuthn' => 'privacyidea:PrivacyideaAuthProc',
    ],
    'token_type_attr' => 'type',
    'preferred_filter' => 'privacyidea:PrivacyideaAuthProc',
    'max_user_capability_attr' => 'maxUserCapability',
    'max_auth' => 'https://id.muni.cz/profile/maxAuth',
    //'password_contexts' => array_merge(AuthSwitcher::PASSWORD_CONTEXTS, [
    //    'my-custom-authn-context-for-password',
    //    '/^my-regex-.*/',
    //]),
    //'mfa_contexts' => array_merge(AuthSwitcher::MFA_CONTEXTS, [
    //    'my-custom-authn-context-for-mfa',
    //]),
    //'contexts_regex' => true,
];
