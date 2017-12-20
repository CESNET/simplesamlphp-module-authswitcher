<?php
/** Definition for filter yubikey:OTP
 * @see https://github.com/simplesamlphp/simplesamlphp-module-yubikey */
class aswAuthFilterMethod_yubikey_OTP extends sspmod_authswitcher_AuthFilterMethodWithSimpleSecret {
    /** @override */
    public function getTargetFieldName() {
        return 'yubikey';
    }
}

/** Definition for filter simpletotp:2fa
 * @see https://github.com/aidan-/SimpleTOTP */
class aswAuthFilterMethod_simpletotp_2fa extends sspmod_authswitcher_AuthFilterMethodWithSimpleSecret {
    /** @override */
    public function getTargetFieldName() {
        return 'totp_secret';
    }
}

/** Definition for filter authTiqr:Tiqr */
class aswAuthFilterMethod_authTiqr_Tiqr extends sspmod_authswitcher_AuthFilterMethod {
    /** @override */
    public function process(&$request) {
        // TODO
    }
    
    /** @override */
    public function __construct(sspmod_authswitcher_MethodParams $methodParams) {
        // TODO
    }
}
