<?php
namespace SimpleSAML\Module\authswitcher;

/** Module-wide constants. */
class AuthSwitcher
{
    /** Name of the uid attribute. */
    const UID_ATTR = 'uid';
    /** Name of the MFA being performed attribute. */
    const MFA_BEING_PERFORMED = 'mfa_being_performed';
    /** Minimal factor. */
    const FACTOR_MIN = \SimpleSAML\Module\authswitcher\AuthSwitcherFactor::SECOND;

    /** REFEDS profile for SFA */
    const SFA = 'https://refeds.org/profile/sfa';
    /** REFEDS profile for MFA */
    const MFA = 'https://refeds.org/profile/mfa';

    /** Password AuthnContext */
    const PASS = 'urn:oasis:names:tc:SAML:2.0:ac:classes:Password';

    /** Supported AuthnContexts (pass <= sfa < mfa) */
    const SUPPORTED = array(self::PASS, self::SFA, self::MFA);

    const SAML2_STATUS_RESPONDER = 'urn:oasis:names:tc:SAML:2.0:status:Responder';
    const SAML2_STATUS_REQUESTER = 'urn:oasis:names:tc:SAML:2.0:status:Requester';
}
