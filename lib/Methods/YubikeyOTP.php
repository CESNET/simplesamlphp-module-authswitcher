<?php
namespace SimpleSAML\Module\authswitcher\Methods;

/** Definition for filter yubikey:OTP
 * @see https://github.com/simplesamlphp/simplesamlphp-module-yubikey */
class YubikeyOTP extends \SimpleSAML\Module\authswitcher\AuthFilterMethodWithSimpleSecret
{
    /* constants */
    const ASSURANCE_ATTR_NAME = 'yubikeyAssurance';
    const ASSURANCE_ATTR_VALUE = 'OTP';

    /** @override */
    public function getTargetFieldName()
    {
        return 'yubikey';
    }

    /** @override */
    public function wasPerformed(&$state)
    {
        return isset($state['Attributes'][self::ASSURANCE_ATTR_NAME])
            && $state['Attributes'][self::ASSURANCE_ATTR_NAME][0] === self::ASSURANCE_ATTR_VALUE;
    }
}
