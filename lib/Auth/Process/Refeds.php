<?php
require_once __DIR__ . '/../../defaultAuthFilterMethods.php';

/** Authentication processing filter for complying with the REFEDS Assurance Framework.
 * @see https://wiki.refeds.org/display/ASS/REFEDS+Assurance+Framework+ver+1.0 */
class sspmod_authswitcher_Auth_Process_Refeds extends SimpleSAML_Auth_ProcessingFilter {
    /* constants */
    const DEBUG_PREFIX = 'authswitcher:Refeds: ';

    /** @override */
    public function process(&$state) {
        $mfaPerformed = $this->wasMFAPerformed($state);
        $this->addRefedsAttributes($mfaPerformed, $state);
    }

    /** Check if the MFA auth proc filters (which were run) finished successfully.
      * If everything is configured correctly, this should not throw an exception. */
    private function wasMFAPerformed(&$state) {
        $mfaPerformed = false;
        if (isset($state[sspmod_authswitcher_AuthSwitcher::MFA_BEING_PERFORMED])) {
            foreach($state[sspmod_authswitcher_AuthSwitcher::MFA_BEING_PERFORMED] as $method) {
                if ($this->wasAuthProcFilterRun($method, $state)) {
                    $mfaPerformed = true;
                } else {
                    throw new SimpleSAML_Error_Exception(self::DEBUG_PREFIX . 'The auth proc filter ' . $method->method . ' did not run. This probably means invalid configuration.');
                }
            }
        }
        return $mfaPerformed;
    }

    /** Check if an auth proc filter was actually run */
    private function wasAuthProcFilterRun(sspmod_authswitcher_MethodParams $method, &$state) {
        list($module, $simpleClass) = explode(":", $method->method);
        $filterMethodClassName = "aswAuthFilterMethod_" . $module . "_" . $simpleClass;
        $filterMethod = new $filterMethodClassName($method);
        return $filterMethod->wasPerformed($state);
    }

    /** Add attributes to eduPersonAssurance considering SFA/MFA.
      * It is assumed that SFA and MFA are exclusive (users with MFA enabled must use it every time). */
    private function addRefedsAttributes($mfaPerformed, &$state) {
        if ($mfaPerformed) {
            $state['saml:AuthnContextClassRef'] = "https://refeds.org/profile/mfa";
            $state['Attributes']['eduPersonAssurance'][] = 'https://refeds.org/assurance/IAP/high';
            $state['Attributes']['eduPersonAssurance'][] = 'https://refeds.org/assurance/profile/espresso';
        } else {
            $state['saml:AuthnContextClassRef'] = "https://refeds.org/profile/sfa";
            $state['Attributes']['eduPersonAssurance'][] = 'https://refeds.org/assurance/profile/cappuccino';
        }
    }
}
