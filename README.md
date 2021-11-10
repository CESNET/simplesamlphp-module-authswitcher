# SimpleSAMLPHP module authswitcher

Module for toggling [WebAuthn](https://github.com/CESNET/simplesamlphp-module-webauthn) and [TOTP](https://gitlab.ics.muni.cz/perun/proxyaai/simplesamlphp/simplesamlphp-module-totp) [authentication processing filters](https://simplesamlphp.org/docs/stable/simplesamlphp-authproc) and honoring + setting `AuthnContextClassRef`, both for IdP and proxy.

## Install

You have to install this module using Composer together with SimpleSAMLphp so that dependencies are installed as well.

The module is enabled by default.

The modules that are going to be controlled by authswitcher need to be installed separately.

## Use (configure as auth proc filter)

Add an instance of the auth proc filter `authswitcher:SwitchAuth`:

```php
53 => [
    'class' => 'authswitcher:SwitchAuth',
    'configs' => [
        'totp:Totp' => [
            'secret_attr' => 'totp_secret',
            'enforce_2fa' => true,
            'skip_redirect_url' => 'https://id.muni.cz/simplesaml/module.php/authswitcher/switchMfaMethods.php',
        ],
        'webauthn:WebAuthn' => [
            'redirect_url' => 'https://id.muni.cz/webauthn/authentication_request',
            'api_url' => 'https://id.muni.cz/webauthn/request',
            'signing_key' => 'webauthn_private.pem',
            'user_id' => 'uniqueId',
            'skip_redirect_url' => 'https://id.muni.cz/simplesaml/module.php/authswitcher/switchMfaMethods.php',
        ],
    ],
],
// as a safety precausion, remove the "secret" attributes
54 => [
    'class' => 'core:AttributeAlter',
    'subject' => 'totp_secret',
    'pattern' => '/.*/',
    '%remove',
],
```

## MFA tokens

This module expects that there will be a user attribute (`$attributes` aka `$state['Attributes']`) with key `"mfaTokens"` filled with the tokens registered by the supported 2FA modules (array of JSON strings).

When MFA is run, if there is at least one MFA token which is not revoked and either `"mfaEnforce"` user attribute is set or MFA is preferred by SP (from `AuthnContext`), the `SwitchAuth` auth proc filter runs one of the configured supported 2FA modules, decided by type of user's MFA tokens. If more than one token types are available, the 2FA method is decided by device type (TOTP is preferred for mobile devices, WebAuthn for desktops and laptops).

If the user has multiple token types, it is possible to switch between them. The supported MFA modules redirect to `switchMfaMethods.php`, which checks authentication result and if MFA has not completed, it runs the next 2FA method filter.

## Running in proxy mode

In the proxy mode, it is assumed that the upstream IdP used for authentication could handle the requested `AuthnContext` already. You just need to set the `proxy_mode` configuration option to `true`:

```php
53 => [
    'class' => 'authswitcher:SwitchAuth',
    'proxy_mode' => true,
    'configs' => [
        // ...
    ],
]
```

© 2017-2021 CSIRT-MU
