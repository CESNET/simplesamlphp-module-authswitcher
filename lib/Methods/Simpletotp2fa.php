<?php
namespace SimpleSAML\Module\authswitcher\Methods;

/** Definition for filter simpletotp:2fa
 * @see https://github.com/aidan-/SimpleTOTP */
class Simpletotp2fa extends \SimpleSAML\Module\authswitcher\AuthFilterMethodWithSimpleSecret
{
    /** @override */
    public function getTargetFieldName()
    {
        return 'totp_secret';
    }

    /** @override */
    public function wasPerformed(&$state)
    {
        return isset($state['2fa_secret']);
    }
}
