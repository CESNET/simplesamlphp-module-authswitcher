# SimpleSAMLPHP module authswitcher

Module for switching between different MFA modules. Tested
with [PrivacyIDEA](https://github.com/xpavlic/simplesamlphp-module-privacyidea)
, [WebAuthn](https://github.com/CESNET/simplesamlphp-module-webauthn)
and [TOTP](https://gitlab.ics.muni.cz/perun/proxyaai/simplesamlphp/simplesamlphp-module-totp) [authentication processing filters](https://simplesamlphp.org/docs/stable/simplesamlphp-authproc)
and honoring + setting `AuthnContextClassRef`, both for IdP and proxy.

## Install

You have to install this module using Composer together with SimpleSAMLphp so that dependencies are installed as well.

The module is enabled by default.

The modules that are going to be controlled by authswitcher need to be installed separately.

Security consideration: It is assumed that none of the auth proc modules allows user to skip the verification in other way than using the method switching/skipping of this module. If there is such option, you need to disable it, otherwise users can bypass MFA while asserting the REFEDS MFA profile.

## GetMfaTokensPrivacyIDEA auth proc filter

Use this filter to read user mfa tokens from PrivacyIDEA server to state attributes.

```php
52 => [
    'class' => 'authswitcher:GetMfaTokensPrivacyIDEA',
    'config' => [
        'tokens_Attr' => 'privacyIDEATokens',
        'privacy_idea_username' => 'admin',
        'privacy_idea_passwd' => 'secret',
        'privacy_idea_domain' => 'https://mfa.id.muni.cz',
        'tokens_type' => [
            'TOTP',
            'WebAuthn',
        ],
        'user_attribute' => 'eduPersonPrincipalName',
        'token_type_attr' => 'type',
    ],
],
```

To enable caching of the privacyIDEA auth token, add:

```php
    // ...
        'enable_cache' => true, // defaults to false
        'cache_expiration_seconds' => 30 * 60, // defaults to 55 minutes
    // ...
```

## Use (configure as auth proc filter)

Add an instance of the auth proc filter with example configuration `authswitcher:SwitchAuth`:

```php
54 => [
      'class' => 'authswitcher:SwitchAuth',
      'config' => [
          'type_filter_array' => [
              'TOTP' => 'privacyidea:PrivacyideaAuthProc',
              'WebAuthn' => 'privacyidea:PrivacyideaAuthProc',
          ],
          'token_type_attr' => 'type',
          'preferred_filter' => 'privacyidea:PrivacyideaAuthProc',
          'max_user_capability_attr' => 'maxUserCapability',
          'max_auth' => 'https://id.muni.cz/profile/maxAuth',
      ],
      'configs' => [
            'totp:Totp' => [
                'secret_attr' => 'totp_secret',
                'enforce_2fa' => true,
                'skip_redirect_url' => 'https://simplesaml/module.php/authswitcher/switchMfaMethods.php',
            ],
            'webauthn:WebAuthn' => [
                'redirect_url' => 'https://webauthn/authentication_request',
                'api_url' => 'https://webauthn/request',
                'signing_key' => '/var/webauthn_private.pem',
                'user_id' => 'eduPersonPrincipalName',
                'skip_redirect_url' => 'https://simplesaml/module.php/authswitcher/switchMfaMethods.php',
            ],
            'privacyidea:PrivacyideaAuthProc' => [
                'privacyideaServerURL' => 'https://mfa.id.muni.cz',
                'realm'                => 'muni.cz',
                'uidKey'               => 'eduPersonPrincipalName',
                'sslVerifyHost'        => 'true',
                'sslVerifyPeer'        => 'true',
                'serviceAccount'       => 'admin',
                'servicePass'          => 'secret',
                'doEnrollToken'        => 'false',
                'tokenType'            =>  [
                    'webauthn',
                    'totp',
                ],
                'doTriggerChallenge'   => 'true',
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

This module expects that there will be a user attribute (`$attributes` aka `$state['Attributes']`) with
key `"mfaTokens"` filled with the tokens registered by the supported 2FA modules (array of arrays).

example of mfaTokens:

```php
[
    [
        "added"   => "2021-09-06 14:40:06",
        "revoked" => false,
        "secret"  => "topsecret",
        "userId"  => "43215",
        "type"    => "TOTP",
    ],
    [
        // ...
    ],
]
```

When MFA is run, if there is at least one MFA token which is not revoked and either `"mfaEnforce"` user attribute is set
or MFA is preferred by SP (from `AuthnContext`), the `SwitchAuth` auth proc filter runs one of the configured supported
2FA modules, decided by type of user's MFA tokens. If more than one token types are available, the 2FA method is decided
by device type (TOTP is preferred for mobile devices, WebAuthn for desktops and laptops).

If the user has multiple token types, it is possible to switch between them. The supported MFA modules redirect
to `switchMfaMethods.php`, which checks authentication result and if MFA has not completed, it runs the next 2FA method
filter.

## Running in proxy mode

In the proxy mode, it is assumed that the upstream IdP used for authentication could handle the requested `AuthnContext`
already. You just need to set the `proxy_mode` configuration option to `true`:

```php
53 => [
    'class' => 'authswitcher:SwitchAuth',
    'config' => [
        'proxy_mode' => true,
        // ...
    ],
    //...
]
```

## Enforce MFA per user

If a user should only use MFA, set `mfaEnforced` user attribute to a non-empty value. You can fill this attribute any way you like, for example from LDAP or from database.

If the user has no MFA tokens and `mfaEnforced` is non-empty, it is ignored (to prevent lock-outs).

When the attribute is not empty, single factor authentication is not considered. Therefore, when an SP requests `SFA` or `PasswordProtectedTransport` specifically, the authentication will fail.

## Add additional attributes when MFA is performed

To add attributes only if MFA was performed, you can use a filter called `AddAdditionalAttributesAfterMfa`.
This filter sets attributes based on whether MFA was in fact performed (at upstream IdP or locally) - not based on whether MFA is in the response AuthnContext.

`AddAdditionalAttributesAfterMfa` needs to run after the `SwitchAuth` filter.

In configuration, you just need to add a `custom_attrs` option which contains a map of additional attributes and their values.
Also, you can add a `proxy_mode` option as mentioned above.

```php
55 => [
    'class' => 'authswitcher:AddAdditionalAttributesAfterMfa',
    'config' => [
        'custom_attrs' => [
            'attr1' => ['value1'],
            'attr2' => ['value2'],
            // ...
        ],
        // ...
    ],
    //...
]
```

# Copyright

© 2017-2022 Pavel Břoušek, Institute of Computer Science, Masaryk University and CESNET, z. s. p. o. All rights reserved.
