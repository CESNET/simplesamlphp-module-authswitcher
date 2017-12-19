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

The modules that are going to be controlled by authswitcher need to be installed separately.

### Use (configure as auth proc filter)

Add (for example) the following as the first [auth proc filter](https://simplesamlphp.org/docs/stable/simplesamlphp-authproc#section_1):

```php
'1' => array(
    'class' => 'authswitcher:SwitchAuth',
    'dataAdapterConfig' => array( // parameters passed to DataAdapter's constructor
        'dsn' => 'mysql:dbname=foobar;host=127.0.0.1', // change to match your database settings
        'user' => 'foo', // change to database username
        'pass' => 'bar', // change to database password
    ),
    'configs' => array(
        'yubikey:OTP' => array(
            'api_client_id' => '12345', // change to your API client ID
            'api_key' => 'abcdefghchijklmnopqrstuvwxyz', // change to your API key
            'abort_if_missing' => true,
        ),
        'simpletotp:2fa' => array(
        ),
    ),
),
```

Currently the module contain's a `DataAdapter` which connects to an SQL database.

Do *NOT* add separate filters for the authentication methods that are controlled by authswitcher.

© 2017 CSIRT-MU
