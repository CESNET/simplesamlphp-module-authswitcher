# [10.5.0](https://github.com/CESNET/simplesamlphp-module-authswitcher/compare/v10.4.0...v10.5.0) (2022-06-02)


### Features

* entropy of user password is now checked by attributes passed from authsource ([06028eb](https://github.com/CESNET/simplesamlphp-module-authswitcher/commit/06028ebae52df57401554d62e07b6535cca94bfa))

# [10.4.0](https://github.com/CESNET/simplesamlphp-module-authswitcher/compare/v10.3.0...v10.4.0) (2022-06-01)


### Features

* set AuthnContext in proxy mode, MFA from upstream for maxUserCapability ([b8b574b](https://github.com/CESNET/simplesamlphp-module-authswitcher/commit/b8b574bc7483f0c048b41e4088f10b73511e0a19))

# [10.3.0](https://github.com/CESNET/simplesamlphp-module-authswitcher/compare/v10.2.2...v10.3.0) (2022-04-14)


### Bug Fixes

* correct behavior for mfa_preferred_privacyidea_fail=true ([f52389b](https://github.com/CESNET/simplesamlphp-module-authswitcher/commit/f52389be92983e56445b9d4490b25ff03454fc4b))


### Features

* realm option for GetMfaTokensPrivacyIDEA ([c49bfd8](https://github.com/CESNET/simplesamlphp-module-authswitcher/commit/c49bfd8058cebaf8c9bd1652554d0dc51f65000d))
* timeout and connect_timeout options ([e59d98a](https://github.com/CESNET/simplesamlphp-module-authswitcher/commit/e59d98a426c9106452f4068cd9985dc82669f3b9))

## [10.2.2](https://github.com/CESNET/simplesamlphp-module-authswitcher/compare/v10.2.1...v10.2.2) (2022-04-07)


### Bug Fixes

* read mfaEnforced correctly ([dc5f46a](https://github.com/CESNET/simplesamlphp-module-authswitcher/commit/dc5f46a6a8187ee3ab4af2c6a6f06c7d8f9d9dd2))

## [10.2.1](https://github.com/CESNET/simplesamlphp-module-authswitcher/compare/v10.2.0...v10.2.1) (2022-04-07)


### Bug Fixes

* allow enforced MFA even when only password is requested ([0409553](https://github.com/CESNET/simplesamlphp-module-authswitcher/commit/0409553188aff05ee8bdcc3afb0a463e4e994a1d))
* do not enforce MFA if already done in proxy mode ([a8c5ad9](https://github.com/CESNET/simplesamlphp-module-authswitcher/commit/a8c5ad9e133b356bc736e4d74b60b8cbbf109779))

# [10.2.0](https://github.com/CESNET/simplesamlphp-module-authswitcher/compare/v10.1.0...v10.2.0) (2022-03-30)


### Features

* switchAuth filter now sets maxUserCapability attribute ([77d51f5](https://github.com/CESNET/simplesamlphp-module-authswitcher/commit/77d51f5d4531cbb0f17dc97647def3bc5525e90f))

# [10.1.0](https://github.com/CESNET/simplesamlphp-module-authswitcher/compare/v10.0.0...v10.1.0) (2022-03-29)


### Features

* support Microsoft MFA ([c6f5e92](https://github.com/CESNET/simplesamlphp-module-authswitcher/commit/c6f5e9250d2fca08acb3d8ddc22b5d33b3497004))

# [10.0.0](https://github.com/CESNET/simplesamlphp-module-authswitcher/compare/v9.2.0...v10.0.0) (2022-03-24)


### Bug Fixes

* ignore mfaEnforced when user has no MFA tokens ([c08bebe](https://github.com/CESNET/simplesamlphp-module-authswitcher/commit/c08bebe893e40a4ea583811f05148d4521868710))


### BREAKING CHANGES

* mfaEnforced is ignored for users without MFA tokens

# [9.2.0](https://github.com/CESNET/simplesamlphp-module-authswitcher/compare/v9.1.0...v9.2.0) (2022-02-10)


### Bug Fixes

* wasMFAPerformed checks whether MFA was performed on upstream ([84f01c5](https://github.com/CESNET/simplesamlphp-module-authswitcher/commit/84f01c568dba172f9d7de503218bc77f80fc85bc))


### Features

* AddAdditionalAttributesAfterMfa ([7f6b6b8](https://github.com/CESNET/simplesamlphp-module-authswitcher/commit/7f6b6b844d9db4175126ee37132e91c6f3d633d0))
* restore support for JSON encoded tokens in SwitchAuth ([67c4df4](https://github.com/CESNET/simplesamlphp-module-authswitcher/commit/67c4df424886f37d4eb7f8deb5b1039a7f64eb9e))

# [9.1.0](https://github.com/CESNET/simplesamlphp-module-authswitcher/compare/v9.0.2...v9.1.0) (2022-02-08)


### Features

* cache support for GetMfaTokensPrivacyIDEA ([06f2b21](https://github.com/CESNET/simplesamlphp-module-authswitcher/commit/06f2b21a2578a8a02bb76408b4ec90f5420e0135))

## [9.0.2](https://github.com/CESNET/simplesamlphp-module-authswitcher/compare/v9.0.1...v9.0.2) (2022-01-27)


### Bug Fixes

* first Packagist release ([974afa6](https://github.com/CESNET/simplesamlphp-module-authswitcher/commit/974afa6b1d432528502fb66b076fc1a975a4204e))

## [9.0.1](https://gitlab.ics.muni.cz/perun/proxyaai/simplesamlphp/simplesamlphp-module-authswitcher/compare/v9.0.0...v9.0.1) (2022-01-24)


### Bug Fixes

* set AuthnContext properly ([136ac9c](https://gitlab.ics.muni.cz/perun/proxyaai/simplesamlphp/simplesamlphp-module-authswitcher/commit/136ac9c885cee055c7771403737544c554c9bbb1))

# [9.0.0](https://gitlab.ics.muni.cz/perun/proxyaai/simplesamlphp/simplesamlphp-module-authswitcher/compare/v8.0.1...v9.0.0) (2022-01-21)


### Bug Fixes

* mfa tokens in state attributes have to be array not string ([4a507e5](https://gitlab.ics.muni.cz/perun/proxyaai/simplesamlphp/simplesamlphp-module-authswitcher/commit/4a507e5240859c9292242a5ce8a6d978c7bcf604))


### BREAKING CHANGES

* mfa tokens in state attributes type changed

## [8.0.1](https://gitlab.ics.muni.cz/perun/proxyaai/simplesamlphp/simplesamlphp-module-authswitcher/compare/v8.0.0...v8.0.1) (2022-01-17)


### Bug Fixes

* wrong type in getConfig method ([22a6610](https://gitlab.ics.muni.cz/perun/proxyaai/simplesamlphp/simplesamlphp-module-authswitcher/commit/22a66107415635c8118417dea56a5d864ebaa1f3))

# [8.0.0](https://gitlab.ics.muni.cz/perun/proxyaai/simplesamlphp/simplesamlphp-module-authswitcher/compare/v7.1.1...v8.0.0) (2022-01-15)


### Features

* privacyIDEA support ([c7c9074](https://gitlab.ics.muni.cz/perun/proxyaai/simplesamlphp/simplesamlphp-module-authswitcher/commit/c7c9074cb94c90e6f690b72e81dc58f98a523497))


### BREAKING CHANGES

* changes to configuration of auth proc filter

## [7.1.1](https://gitlab.ics.muni.cz/perun/proxyaai/simplesamlphp/simplesamlphp-module-authswitcher/compare/v7.1.0...v7.1.1) (2022-01-15)


### Bug Fixes

* revert breaking changes ([665986d](https://gitlab.ics.muni.cz/perun/proxyaai/simplesamlphp/simplesamlphp-module-authswitcher/commit/665986d377f1b90e0ab80de8f9c56e5dadb53818))

# [7.1.0](https://gitlab.ics.muni.cz/perun/proxyaai/simplesamlphp/simplesamlphp-module-authswitcher/compare/v7.0.2...v7.1.0) (2022-01-14)


### Features

* added new GetMfaTokensPrivacyIDEA filter, updated config of SwitchAuth filter ([17016a9](https://gitlab.ics.muni.cz/perun/proxyaai/simplesamlphp/simplesamlphp-module-authswitcher/commit/17016a9da92d2765de74d50dd28377d167eae790))

## [7.0.2](https://gitlab.ics.muni.cz/perun/proxyaai/simplesamlphp/simplesamlphp-module-authswitcher/compare/v7.0.1...v7.0.2) (2021-12-07)


### Bug Fixes

* missing import ([59510c9](https://gitlab.ics.muni.cz/perun/proxyaai/simplesamlphp/simplesamlphp-module-authswitcher/commit/59510c9ebaeb79d0d6c04e2aa21dbad9a41974d7))

## [7.0.1](https://gitlab.ics.muni.cz/perun/proxyaai/simplesamlphp/simplesamlphp-module-authswitcher/compare/v7.0.0...v7.0.1) (2021-12-06)


### Bug Fixes

* add missing import, add logging ([98a193d](https://gitlab.ics.muni.cz/perun/proxyaai/simplesamlphp/simplesamlphp-module-authswitcher/commit/98a193dc8d5b18b7601db8e41f4697a913cfb15e))

# [7.0.0](https://gitlab.ics.muni.cz/perun/proxyaai/simplesamlphp/simplesamlphp-module-authswitcher/compare/v6.1.3...v7.0.0) (2021-11-10)


### Features

* proxy mode, refactoring, bug fixes ([4dd3b3f](https://gitlab.ics.muni.cz/perun/proxyaai/simplesamlphp/simplesamlphp-module-authswitcher/commit/4dd3b3feea8f425716794f8b0fd40809e1f9f8d9))


### BREAKING CHANGES

* removed AttributeAlterCallback auth proc filter

## [6.1.3](https://gitlab.ics.muni.cz/perun/proxyaai/simplesamlphp/simplesamlphp-module-authswitcher/compare/v6.1.2...v6.1.3) (2021-10-26)


### Bug Fixes

* fix random bugs with consent module, preventing users to log in for the first time ([83cd30e](https://gitlab.ics.muni.cz/perun/proxyaai/simplesamlphp/simplesamlphp-module-authswitcher/commit/83cd30ec11b7994f57f87bbcabe35f7c3e6c1153)), closes [#5](https://gitlab.ics.muni.cz/perun/proxyaai/simplesamlphp/simplesamlphp-module-authswitcher/issues/5)

## [6.1.2](https://gitlab.ics.muni.cz/perun/proxyaai/simplesamlphp/simplesamlphp-module-authswitcher/compare/v6.1.1...v6.1.2) (2021-10-25)


### Bug Fixes

* fix index error ([7169aad](https://gitlab.ics.muni.cz/perun/proxyaai/simplesamlphp/simplesamlphp-module-authswitcher/commit/7169aad7ac6fc13956583b26613e88e196a7cfe3)), closes [#4](https://gitlab.ics.muni.cz/perun/proxyaai/simplesamlphp/simplesamlphp-module-authswitcher/issues/4)

## [6.1.1](https://gitlab.ics.muni.cz/perun/proxyaai/simplesamlphp/simplesamlphp-module-authswitcher/compare/v6.1.0...v6.1.1) (2021-10-21)


### Bug Fixes

* fix handling unsupported authn contexts ([0bdab33](https://gitlab.ics.muni.cz/perun/proxyaai/simplesamlphp/simplesamlphp-module-authswitcher/commit/0bdab3379718b4fbf7bac5cda9ce7bb494b2e027))

# [6.1.0](https://gitlab.ics.muni.cz/perun/proxyaai/simplesamlphp/simplesamlphp-module-authswitcher/compare/v6.0.0...v6.1.0) (2021-10-21)


### Features

* do not repeat MFA on proxy ([8c2fbcb](https://gitlab.ics.muni.cz/perun/proxyaai/simplesamlphp/simplesamlphp-module-authswitcher/commit/8c2fbcb0603d7c5edfdc1361e351794951548e36)), closes [#3](https://gitlab.ics.muni.cz/perun/proxyaai/simplesamlphp/simplesamlphp-module-authswitcher/issues/3)

# [6.0.0](https://gitlab.ics.muni.cz/perun/proxyaai/simplesamlphp/simplesamlphp-module-authswitcher/compare/v5.0.0...v6.0.0) (2021-10-06)


### Bug Fixes

* reply with one of requested AuthnContextClassRefs ([43cb294](https://gitlab.ics.muni.cz/perun/proxyaai/simplesamlphp/simplesamlphp-module-authswitcher/commit/43cb294ec23ea799c7dcff78c834fcb05318f0e3))


### BREAKING CHANGES

* removed Refeds auth proc filter

# [5.0.0](https://gitlab.ics.muni.cz/perun/proxyaai/simplesamlphp/simplesamlphp-module-authswitcher/compare/v4.1.3...v5.0.0) (2021-08-18)


### Features

* move storage classes to TOTP module ([f933dc5](https://gitlab.ics.muni.cz/perun/proxyaai/simplesamlphp/simplesamlphp-module-authswitcher/commit/f933dc503d043cffedb9b98edc8ffa7796fe98fe))


### BREAKING CHANGES

* removed DatabaseStorage, removed PerunStorage, removed module_authswitcher.php

## [4.1.3](https://gitlab.ics.muni.cz/perun/proxyaai/simplesamlphp/simplesamlphp-module-authswitcher/compare/v4.1.2...v4.1.3) (2021-08-04)


### Bug Fixes

* correct WebAuthn token type case ([9b65ea4](https://gitlab.ics.muni.cz/perun/proxyaai/simplesamlphp/simplesamlphp-module-authswitcher/commit/9b65ea43b608bfd75250edbe6b28185edcc7183d))
* remove indexes from MFA_METHODS ([b1980bd](https://gitlab.ics.muni.cz/perun/proxyaai/simplesamlphp/simplesamlphp-module-authswitcher/commit/b1980bdc33de1786f1bd34dd947be48900f33287))

## [4.1.2](https://gitlab.ics.muni.cz/perun/proxyaai/simplesamlphp/simplesamlphp-module-authswitcher/compare/v4.1.1...v4.1.2) (2021-07-23)


### Bug Fixes

* add logging of MFA API response ([bce33e4](https://gitlab.ics.muni.cz/perun/proxyaai/simplesamlphp/simplesamlphp-module-authswitcher/commit/bce33e4dc0a59efa6b2e606738ff0e4fca0434cb))

## [4.1.1](https://gitlab.ics.muni.cz/perun/proxyaai/simplesamlphp/simplesamlphp-module-authswitcher/compare/v4.1.0...v4.1.1) (2021-07-22)


### Bug Fixes

* allow SFA for users with MFA tokens without MFA enforced ([ff928dd](https://gitlab.ics.muni.cz/perun/proxyaai/simplesamlphp/simplesamlphp-module-authswitcher/commit/ff928ddc6a1ddf76788e26b3ec11a365c68ceb9a))

# [4.1.0](https://gitlab.ics.muni.cz/id.muni.cz/id.muni.cz-authswitcher/compare/v4.0.0...v4.1.0) (2021-05-14)


### Features

* added switching between multiple authentication methods ([c3de9c0](https://gitlab.ics.muni.cz/id.muni.cz/id.muni.cz-authswitcher/commit/c3de9c05ddd6d4f5cdbaed828adf40e23b79e705))
* userid_attribute removed ([3672b0a](https://gitlab.ics.muni.cz/id.muni.cz/id.muni.cz-authswitcher/commit/3672b0a8f04f09a22c6d21414f6099e02617d17d)), closes [#1](https://gitlab.ics.muni.cz/id.muni.cz/id.muni.cz-authswitcher/issues/1)

# [4.0.0](https://gitlab.ics.muni.cz/id.muni.cz/id.muni.cz-authswitcher/compare/v3.4.3...v4.0.0) (2021-04-27)


### Features

* make PerunStorage universal ([1711342](https://gitlab.ics.muni.cz/id.muni.cz/id.muni.cz-authswitcher/commit/17113423ad66980f8aa2f0d3287131c0096fb9c5))
* mfaTokens attribute instead of preferences table ([7275b58](https://gitlab.ics.muni.cz/id.muni.cz/id.muni.cz-authswitcher/commit/7275b58a936f22a9c72824727f845c568fbbb0b0))


### BREAKING CHANGES

* removed usage of preferences table stored in database
* requires changes to config file



## [3.4.3](https://gitlab.ics.muni.cz/id.muni.cz/id.muni.cz-authswitcher/compare/v3.4.2...v3.4.3) (2021-02-22)



## [3.4.2](https://gitlab.ics.muni.cz/id.muni.cz/id.muni.cz-authswitcher/compare/v3.4.1...v3.4.2) (2021-02-22)


### Bug Fixes

* add dependency to composer.json ([60a3bd6](https://gitlab.ics.muni.cz/id.muni.cz/id.muni.cz-authswitcher/commit/60a3bd6fc12142bc9c7f27a0b4ae1171027b646c))



## [3.4.1](https://gitlab.ics.muni.cz/id.muni.cz/id.muni.cz-authswitcher/compare/v3.4.0...v3.4.1) (2021-01-28)


### Bug Fixes

* bug fix ([7782b5e](https://gitlab.ics.muni.cz/id.muni.cz/id.muni.cz-authswitcher/commit/7782b5e07bf783d25e8b1b9e23023e7ebfe1a29b))



# [3.4.0](https://gitlab.ics.muni.cz/id.muni.cz/id.muni.cz-authswitcher/compare/v3.3.1...v3.4.0) (2021-01-28)



## [3.3.1](https://gitlab.ics.muni.cz/id.muni.cz/id.muni.cz-authswitcher/compare/v3.3.0...v3.3.1) (2020-06-10)



# [3.3.0](https://gitlab.ics.muni.cz/id.muni.cz/id.muni.cz-authswitcher/compare/v3.2.0...v3.3.0) (2020-05-29)



# [3.2.0](https://gitlab.ics.muni.cz/id.muni.cz/id.muni.cz-authswitcher/compare/v3.1.1...v3.2.0) (2020-05-25)



## [3.1.1](https://gitlab.ics.muni.cz/id.muni.cz/id.muni.cz-authswitcher/compare/v3.1.0...v3.1.1) (2020-01-17)



# [3.1.0](https://gitlab.ics.muni.cz/id.muni.cz/id.muni.cz-authswitcher/compare/v3.0.1...v3.1.0) (2020-01-15)



## [3.0.1](https://gitlab.ics.muni.cz/id.muni.cz/id.muni.cz-authswitcher/compare/v3.0.0...v3.0.1) (2020-01-14)



# [3.0.0](https://gitlab.ics.muni.cz/id.muni.cz/id.muni.cz-authswitcher/compare/v2.0.0...v3.0.0) (2020-01-14)



# 2.0.0 (2019-12-03)
