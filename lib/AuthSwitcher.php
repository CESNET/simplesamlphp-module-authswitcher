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
     * Name of the support requested contexts attribute.
     */
    public const SUPPORTED_REQUESTED_CONTEXTS = 'authswitcher_supported_requested_contexts';

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

    /**
     * Contexts to assume when request contains none.
     */
    public const DEFAULT_REQUESTED_CONTEXTS = [self::SFA, self::MFA];

    /**
     * Contexts to reply when MFA was performed, in the order of preference.
     */
    public const REPLY_CONTEXTS_MFA = [self::MFA];

    /**
     * Contexts to reply when MFA was not performed, in the order of preference.
     */
    public const REPLY_CONTEXTS_SFA = [self::SFA, self::PASS];

    public const SAML2_STATUS_RESPONDER = 'urn:oasis:names:tc:SAML:2.0:status:Responder';

    public const SAML2_STATUS_REQUESTER = 'urn:oasis:names:tc:SAML:2.0:status:Requester';

    public const ERROR_STATE = 'authswitcher_error_state';
}
