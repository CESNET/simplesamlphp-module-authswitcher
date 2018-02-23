<?php
/** Definition for filter yubikey:OTP
 * @see https://github.com/simplesamlphp/simplesamlphp-module-yubikey */
class aswAuthFilterMethod_yubikey_OTP extends sspmod_authswitcher_AuthFilterMethodWithSimpleSecret {
    /* constants */
    const ASSURANCE_ATTR_NAME = 'yubikeyAssurance';
    const ASSURANCE_ATTR_VALUE = 'OTP';

    /** @override */
    public function getTargetFieldName() {
        return 'yubikey';
    }

    /** @override */
    public function wasPerformed(&$state) {
        return isset($state['Attributes'][self::ASSURANCE_ATTR_NAME]) && $state['Attributes'][self::ASSURANCE_ATTR_NAME][0] === self::ASSURANCE_ATTR_VALUE;
    }
}

/** Definition for filter simpletotp:2fa
 * @see https://github.com/aidan-/SimpleTOTP */
class aswAuthFilterMethod_simpletotp_2fa extends sspmod_authswitcher_AuthFilterMethodWithSimpleSecret {
    /** @override */
    public function getTargetFieldName() {
        return 'totp_secret';
    }

    /** @override */
    public function wasPerformed(&$state) {
        return isset($state['2fa_secret']);
    }
}

/** Definition for filter authTiqr:Tiqr */
class aswAuthFilterMethod_authTiqr_Tiqr extends sspmod_authswitcher_AuthFilterMethod {
    /** @override */
    public function process(&$state) {
        // TODO
    }
    
    /** @override */
    public function __construct(sspmod_authswitcher_MethodParams $methodParams) {
        // TODO
    }

    /** @override */
    public function wasPerformed(&$state) {
        // TODO
    }
}
