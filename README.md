# SimpleSAMLPHP module authswitcher

Module for toggling [WebAuthn](https://github.com/CESNET/simplesamlphp-module-webauthn) and [TOTP](https://gitlab.ics.muni.cz/perun/proxyaai/simplesamlphp/simplesamlphp-module-totp) [authentication processing filters](https://simplesamlphp.org/docs/stable/simplesamlphp-authproc).

The module was tested on a Debian 9 server with PHP 7.4 and SSP 1.18.3.

## Install

You have to install this module using Composer together with SimpleSAMLphp so that dependencies are installed as well.

The module is enabled by default.

The modules that are going to be controlled by authswitcher need to be installed separately.

## Use (configure as auth proc filter)

Add an instance of the auth proc filter `authswitcher:SwitchAuth`:

```php
54 => [
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
53 => [
    'class' => 'core:AttributeAlter',
    'subject' => 'totp_secret',
    'pattern' => '/.*/',
    '%remove',
],
// REFEDS
55 => [
    'class' => 'core:AttributeAdd',
    'eduPersonAssurance' => [
        'https://refeds.org/assurance',
        'https://refeds.org/assurance/ID/unique',
        'https://refeds.org/assurance/ID/eppn-unique-no-reassign',
        'https://refeds.org/assurance/IAP/local-enterprise',
        'https://refeds.org/assurance/ATP/ePA-1m',
        'https://refeds.org/assurance/ATP/ePA-1d',
        'https://refeds.org/assurance/IAP/low',
        'https://refeds.org/assurance/IAP/medium',
    ],
],
60 => [
    'class' => 'authswitcher:Refeds',
],

```

If Attributes array contains at least one mfaToken which is not revoked and mfaEnforce attribute is set or mfa is preferred by SP, SwitchAuth proc filter runs [WebAuthn](https://github.com/CESNET/simplesamlphp-module-webauthn) or
[TOTP](https://gitlab.ics.muni.cz/perun/proxyaai/simplesamlphp/simplesamlphp-module-totp) proc filter, decided by type of mfaToken. If both token types are available, proc filter is decided by type of running device (TOTP for mobile devices, WebAuthn for desktops and laptops).

It is possible to redirect from [WebAuthn](https://github.com/CESNET/simplesamlphp-module-webauthn) and [TOTP](https://gitlab.ics.muni.cz/perun/proxyaai/simplesamlphp/simplesamlphp-module-totp) to switchMfaMethods script, which checks authentication result and if result is negative, it runs next proc filter if available.

## Extend this module

### Custom AuthFilterMethod

If you want to add a new MFA method, create a class whose name is in the form `\SimpleSAML\Module\authswitcher\*Modulenamefiltername*` and it extends [\SimpleSAML\Module\authswitcher\AuthFilterMethod](https://gitlab.ics.muni.cz/id.muni.cz/id.muni.cz-authswitcher/blob/master/lib/AuthFilterMethod.php).
For example for a filter named `bar` of a module named `foo`:

```php
class \SimpleSAML\Module\authswitcher\Foobar extends \SimpleSAML\Module\authswitcher\AuthFilterMethod {
    /* ... */
}
```

Then configure authswitcher to use filter `foo:bar` and this class will be used.

© 2017-2019 CSIRT-MU
