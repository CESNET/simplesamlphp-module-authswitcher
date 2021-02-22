# SimpleSAMLPHP module authswitcher

Module for toggling [authentication processing filters](https://simplesamlphp.org/docs/stable/simplesamlphp-authproc) on a per-user basis (e.g. [YubiKey](https://github.com/simplesamlphp/simplesamlphp-module-yubikey) or [TOTP](https://github.com/aidan-/SimpleTOTP)).

Example: One user only authenticates with username and password, second uses password and YubiKey and third user logs in with password and TOTP.

The settings are retrieved using `\SimpleSAML\Database`.

The module was tested on a Debian 9 server with PHP 7.4 and SSP 1.18.3.

## Install

You have to install this module using Composer together with SimpleSAMLphp so that dependencies are installed as well.

The module is enabled by default.

The modules that are going to be controlled by authswitcher need to be installed separately.

## Use (configure as auth proc filter)

Add an instance of the auth proc filter `authswitcher:SwitchAuth`:

```php
50 => array(
    'class' => 'authswitcher:SwitchAuth',
    'configs' => array(
        'yubikey:OTP' => array(
            'api_client_id' => '12345', // change to your API client ID
            'api_key' => 'abcdefghchijklmnopqrstuvwxyz', // change to your API key
            'key_id_attribute' => 'yubikey',
            'abort_if_missing' => true,
            'assurance_attribute' => 'yubikeyAssurance',
        ),
        'simpletotp:2fa' => array(
            'secret_attr' => 'totp_secret',
            'enforce_2fa' => true,
        ),
    ),
),
// as a safety precausion, remove the "secret" attributes
52 => array(
    'class' => 'core:AttributeAlter',
    'subject' => 'yubikey',
    'pattern' => '/.*/',
    '%remove',
),
53 => array(
    'class' => 'core:AttributeAlter',
    'subject' => 'totp_secret',
    'pattern' => '/.*/',
    '%remove',
),
// REFEDS
55 => array(
    'class' => 'core:AttributeAdd',
    'eduPersonAssurance' => array(
        'https://refeds.org/assurance',
        'https://refeds.org/assurance/ID/unique',
        'https://refeds.org/assurance/ID/eppn-unique-no-reassign',
        'https://refeds.org/assurance/IAP/local-enterprise',
        'https://refeds.org/assurance/ATP/ePA-1m',
        'https://refeds.org/assurance/ATP/ePA-1d',
        'https://refeds.org/assurance/IAP/low',
        'https://refeds.org/assurance/IAP/medium',
    ),
),
60 => array(
    'class' => 'authswitcher:Refeds',
),

```

IMPORTANT: The modules MUST enforce 2FA. Also, the modules have to handle multiple tokens if desired.

Copy the file `modules/authswitcher/config-templates/module_authswitcher.php` to `config/module_authswitcher.php` and adjust its contents:
```bash
cp modules/authswitcher/config-templates/module_authswitcher.php config/module_authswitcher.php
nano config/module_authswitcher.php
```

```php
<?php
/**
 * This file is part of the authswitcher module.
 */

$config = array(
    'dataAdapter' => '', // adjust
);
```

The authapi module provides a `DataAdapter` implementation which connects to an SQL database. You can also write your [custom DataAdapter](#custom-dataadapter).
Authswitcher includes out-of-the-box support for yubikey:OTP and simpletotp:2fa. To add more, you have to write [custom AuthFilterMethod](#custom-authfiltermethod)s.

Note: Do *NOT* add separate filters for the authentication methods that are controlled by authswitcher.

## Extend this module

### Custom DataAdapter

You can write our own implementation of the [\SimpleSAML\Module\authswitcher\DataAdapter](https://gitlab.ics.muni.cz/id.muni.cz/id.muni.cz-authswitcher/blob/master/lib/DataAdapter.php) interface:

```php
class MyDataAdapter implements \SimpleSAML\Module\authswitcher\DataAdapter {
    /* ... */
}
```

If you make this class available (loaded), you can then setup authswitcher to use it (in `config/module_authswitcher.php`):
```php
<?php
/**
 * This file is part of the authswitcher module.
 */

$config = array(
    'dataAdapter' => 'MyDataAdapter',
);
```

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
