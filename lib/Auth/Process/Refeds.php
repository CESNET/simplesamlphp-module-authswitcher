<?php

namespace SimpleSAML\Module\authswitcher\Auth\Process;

use SimpleSAML\Module\authswitcher\AuthSwitcher;

/**
 * Authentication processing filter for complying with the REFEDS Assurance Framework.
 *
 * @see https://wiki.refeds.org/display/ASS/REFEDS+Assurance+Framework+ver+1.0
 */
class Refeds extends \SimpleSAML\Auth\ProcessingFilter
{
    /**
     * @override
     */
    public function process(&$state)
    {
        $mfaPerformed = $this->wasMFAPerformed($state);
        $this->addRefedsAttributes($mfaPerformed, $state);
    }

    /**
     * Check if the MFA auth proc filters (which were run) finished successfully. If everything is configured correctly,
     * this should not throw an exception.
     */
    private function wasMFAPerformed(&$state)
    {
        return ! empty($state[AuthSwitcher::MFA_BEING_PERFORMED]);
    }

    /**
     * Add attributes to eduPersonAssurance considering SFA/MFA. It is assumed that SFA and MFA are exclusive (users
     * with MFA enabled must use it every time).
     */
    private function addRefedsAttributes($mfaPerformed, &$state)
    {
        if ($mfaPerformed) {
            $state['saml:AuthnContextClassRef'] = 'https://refeds.org/profile/mfa';
            $state['Attributes']['eduPersonAssurance'][] = 'https://refeds.org/assurance/IAP/high';
            $state['Attributes']['eduPersonAssurance'][] = 'https://refeds.org/assurance/profile/espresso';
        } else {
            $state['saml:AuthnContextClassRef'] = 'https://refeds.org/profile/sfa';
            $state['Attributes']['eduPersonAssurance'][] = 'https://refeds.org/assurance/profile/cappuccino';
        }
    }
}
