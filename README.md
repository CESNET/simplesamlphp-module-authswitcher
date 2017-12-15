# SimpleSAMLPHP module authswitcher

Module for toggling [authentication processing filters](https://simplesamlphp.org/docs/stable/simplesamlphp-authproc) on a per-user basis (e.g. [YubiKey](https://github.com/simplesamlphp/simplesamlphp-module-yubikey) or [TOTP](https://github.com/aidan-/SimpleTOTP)).

Example: One user only authenticates with username and password, second uses password and YubiKey and third user logs in with password and TOTP.

It does not handle the settings, which can be done using the [authapi module](https://gitlab.ics.muni.cz/id.muni.cz/id.muni.cz-authapi).
This module retrieves the settings using class DataAdapter.

## How to

### Install

Copy the contents of this repository (folder) into a folder named `authswitcher` in your SSP installation's `modules` folder.

```
cd modules
git clone https://gitlab.ics.muni.cz/id.muni.cz/id.muni.cz-authswitcher.git authswitcher
```

The module is enabled by default.

### Use (configure as auth resource)

Add (for example) the following to `config/authsources.php` (into the `$config` array):

```php
'authswitcherinstance' => array(
    'authswitcher:SwitchAuth',
    'dataAdapterConfig' => array(
        'db_dsn' => 'mysql:dbname=foobar;host=127.0.0.1', // change to match your database settings
        'db_user' => 'foo', // change to database username
        'db_pass' => 'bar', // change to database password
    ),
    'configs' => array(
        'yubikey:OTP' => array(
            'api_client_id' => '12345', // change to your API client ID
            'api_key' => 'abcdefghchijklmnopqrstuvwxyz', // change to your API key
        ),
        'simpletotp:2fa' => array(
        ),
    ),
),
```

Do *NOT* add separate filters for the authentication methods that are controlled by authswitcher.

### Use in an IdP

Add the following to `metadata/saml20-idp-hosted.php` (into `$metadata['__DYNAMIC:1__']` or similar):

```php
    'auth' => 'authswitcherinstance' // as configured in config/authsources.php
```


© 2017 CSIRT-MU
