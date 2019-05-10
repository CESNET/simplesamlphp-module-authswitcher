# SimpleSAMLPHP module authswitcher

Module for toggling [authentication processing filters](https://simplesamlphp.org/docs/stable/simplesamlphp-authproc) on a per-user basis (e.g. [YubiKey](https://github.com/simplesamlphp/simplesamlphp-module-yubikey) or [TOTP](https://github.com/aidan-/SimpleTOTP)).

Example: One user only authenticates with username and password, second uses password and YubiKey and third user logs in with password and TOTP.

It does not handle the settings, which can be done using the [authapi module](https://gitlab.ics.muni.cz/id.muni.cz/id.muni.cz-authapi).
This module retrieves the settings using class DataAdapter.

The module was tested on a Debian 9 server with PHP 7.0 and SSP 1.16.3.

## Install

Copy the contents of this repository (folder) into a folder named `authswitcher` in your SSP installation's `modules` folder.

```
cd modules
git clone https://gitlab.ics.muni.cz/id.muni.cz/id.muni.cz-authswitcher.git authswitcher
```

The module is enabled by default.

The modules that are going to be controlled by authswitcher need to be installed separately.

## Use (configure as auth proc filter)

For each possible authentication step, you have to add an instance of the auth proc filter `authswitcher:SwitchAuth`.
E.g. for 3FA you have to add 2 instances, one with `factor` set to `2` (which is the default) and the other with `factor` set to `3`.

Add (for example) the following as the first [auth proc filter](https://simplesamlphp.org/docs/stable/simplesamlphp-authproc#section_1):

```php
1 => array(
    'class' => 'authswitcher:SwitchAuth',
    'factor' => 2, // default
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
2 => array(
    'class' => 'core:AttributeAlter',
    'subject' => 'yubikey',
    'pattern' => '/.*/',
    '%remove',
),
3 => array(
    'class' => 'core:AttributeAlter',
    'subject' => 'totp_secret',
    'pattern' => '/.*/',
    '%remove',
),
// REFEDS
10 => array(
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
15 => array(
    'class' => 'authswitcher:Refeds',
),

```

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

All MFA modules should enforce 2FA etc. as they are only run for users that have turned them on.

The authapi module provides a `DataAdapter` implementation which connects to an SQL database. You can also write your [custom DataAdapter](#custom-dataadapter).
Authswitcher includes out-of-the-box support for yubikey:OTP and simpletotp:2fa. To add more, you have to write [custom AuthFilterMethod](#custom-authfiltermethod)s.

Note: Do *NOT* add separate filters for the authentication methods that are controlled by authswitcher.

## Extend this module

### Custom DataAdapter

You can write our own implementation of the [sspmod_authswitcher_DataAdapter](https://gitlab.ics.muni.cz/id.muni.cz/id.muni.cz-authswitcher/blob/master/lib/DataAdapter.php) interface:

```php
class MyDataAdapter implements sspmod_authswitcher_DataAdapter {
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

If you want to add a new MFA method, create a class whose name is in the form `aswAuthFilterMethod_*modulename*_*filtername*` and it extends [sspmod_authswitcher_AuthFilterMethod](https://gitlab.ics.muni.cz/id.muni.cz/id.muni.cz-authswitcher/blob/master/lib/AuthFilterMethod.php).
For example for a filter named `bar` of a module named `foo`:
```php
class aswAuthFilterMethod_foo_bar extends sspmod_authswitcher_AuthFilterMethod {
    /* ... */
}
```

Then configure authswitcher to use filter `foo:bar` and this class will be used.


© 2017-2019 CSIRT-MU
