# SimpleSAMLPHP module authswitcher

Module for switching between authentication methods on a per-user basis (e.g. [YubiKey](https://github.com/simplesamlphp/simplesamlphp-module-yubikey) or [TOTP](https://github.com/NIIF/simplesamlphp-module-authtfaga)).

This module's main function is to run 2FA modules (probably as [Auth Proc Filters](https://simplesamlphp.org/docs/stable/simplesamlphp-authproc)) depending on users' settings.

Example: One user only authenticates with username and password, second uses password and YubiKey and third user logs in with password and TOTP.

It does not handle the settings, which can be done using the [authapi module](https://gitlab.ics.muni.cz/id.muni.cz/id.muni.cz-authapi).

## How to

### Install

Copy the contents of this repository (folder) into a folder named `authswitcher` in your SSP installation's `modules` folder.

```
cd modules
git clone https://gitlab.ics.muni.cz/id.muni.cz/id.muni.cz-authswitcher.git authswitcher
```

The module is enabled by default.

### Use (configure as auth resource)

Add the following to `config/authsources.php` (into the `$config` array):

```php
'authswitcherinstance' => array(
    'authswitcher:SwitchAuth',
),
```

### Use in an IdP

Add the following to `metadata/saml20-idp-hosted.php` (into `$metadata['__DYNAMIC:1__']` or similar):

```php
    'auth' => 'authswitcherinstance' // as configured in config/authsources.php
```


© 2017 CSIRT-MU
