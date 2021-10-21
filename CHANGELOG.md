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
