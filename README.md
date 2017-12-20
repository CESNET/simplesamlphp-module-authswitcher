# SimpleSAMLPHP module authswitcher

Module for toggling [authentication processing filters](https://simplesamlphp.org/docs/stable/simplesamlphp-authproc) on a per-user basis (e.g. [YubiKey](https://github.com/simplesamlphp/simplesamlphp-module-yubikey) or [TOTP](https://github.com/aidan-/SimpleTOTP)).

Example: One user only authenticates with username and password, second uses password and YubiKey and third user logs in with password and TOTP.

It does not handle the settings, which can be done using the [authapi module](https://gitlab.ics.muni.cz/id.muni.cz/id.muni.cz-authapi).
This module retrieves the settings using class DataAdapter.

## Install

Copy the contents of this repository (folder) into a folder named `authswitcher` in your SSP installation's `modules` folder.

```
cd modules
git clone https://gitlab.ics.muni.cz/id.muni.cz/id.muni.cz-authswitcher.git authswitcher
```

The module is enabled by default.

The modules that are going to be controlled by authswitcher need to be installed separately.

## Use (configure as auth proc filter)

Add (for example) the following as the first [auth proc filter](https://simplesamlphp.org/docs/stable/simplesamlphp-authproc#section_1):

```php
'1' => array(
    'class' => 'authswitcher:SwitchAuth',
    'dataAdapterClassName' => 'sspmod_authapi_DbDataAdapter',
    'dataAdapterConfig' => array( // parameters passed to DataAdapter's constructor
        'dsn' => 'mysql:dbname=foobar;host=127.0.0.1', // change to match your database settings
        'user' => 'foo', // change to database username
        'pass' => 'bar', // change to database password
    ),
    'configs' => array(
        'yubikey:OTP' => array(
            'api_client_id' => '12345', // change to your API client ID
            'api_key' => 'abcdefghchijklmnopqrstuvwxyz', // change to your API key
            'key_id_attribute' => 'yubikey',
            'abort_if_missing' => true,
        ),
        'simpletotp:2fa' => array(
            'secret_attr' => 'totp_secret',
            'enforce_2fa' => true,
        ),
    ),
),
// and as a safety precausion, remove the "secret" attributes
98 => array(
    'class' => 'core:AttributeAlter',
    'subject' => 'yubikey',
    'pattern' => '/.*/',
    '%remove',
),
99 => array(
    'class' => 'core:AttributeAlter',
    'subject' => 'totp_secret',
    'pattern' => '/.*/',
    '%remove',
),
```

All MFA modules should enforce 2FA etc. as they are only run for users that have turned them on.

By default the module uses a `DataAdapter` implementation which connects to an SQL database. You can also write your [custom DataAdapter](#custom-dataadapter).
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

If you make this class available (loaded), you can then setup authswitcher to use it:
```php
'1' => array(
    'class' => 'authswitcher:SwitchAuth',
    /* ... */
    'dataAdapterClassName' => 'MyDataAdapter',
    /* ... */
),
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


© 2017 CSIRT-MU
