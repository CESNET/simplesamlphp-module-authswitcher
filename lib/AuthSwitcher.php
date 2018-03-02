<?php
/** Module-wide constants. */
class sspmod_authswitcher_AuthSwitcher {
    /** Name of the uid attribute. */
    const UID_ATTR = 'uid';
    /** Name of the MFA being performed attribute. */
    const MFA_BEING_PERFORMED = 'mfa_being_performed';
    /** Minimal factor. */
    const FACTOR_MIN = sspmod_authswitcher_AuthSwitcherFactor::SECOND;

    /** REFEDS profile for SFA */
    const SFA = 'https://refeds.org/profile/sfa';
    /** REFEDS profile for MFA */
    const MFA = 'https://refeds.org/profile/mfa';
}
