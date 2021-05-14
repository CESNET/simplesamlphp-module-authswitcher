<?php

namespace SimpleSAML\Module\authswitcher;

/**
 * Module-wide constants.
 */
class AuthSwitcher
{
    /**
     * Name of the MFA being performed attribute.
     */
    public const MFA_BEING_PERFORMED = 'mfa_being_performed';

    /**
     * REFEDS profile for SFA
     */
    public const SFA = 'https://refeds.org/profile/sfa';

    /**
     * REFEDS profile for MFA
     */
    public const MFA = 'https://refeds.org/profile/mfa';

    /**
     * Password AuthnContext
     */
    public const PASS = 'urn:oasis:names:tc:SAML:2.0:ac:classes:PasswordProtectedTransport';

    /**
     * Supported AuthnContexts (pass <= sfa < mfa)
     */
    public const SUPPORTED = [self::PASS, self::SFA, self::MFA];

    public const SAML2_STATUS_RESPONDER = 'urn:oasis:names:tc:SAML:2.0:status:Responder';

    public const SAML2_STATUS_REQUESTER = 'urn:oasis:names:tc:SAML:2.0:status:Requester';
}
