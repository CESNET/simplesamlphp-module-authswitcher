<?php

declare(strict_types=1);

namespace SimpleSAML\Module\authswitcher;

use SAML2\Constants;

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
     * REFEDS profile for SFA.
     */
    public const SFA = 'https://refeds.org/profile/sfa';

    /**
     * REFEDS profile for MFA.
     */
    public const MFA = 'https://refeds.org/profile/mfa';

    /**
     * Supported AuthnContexts (pass <= sfa < mfa).
     */
    public const SUPPORTED = [Constants::AC_PASSWORD_PROTECTED_TRANSPORT, self::SFA, self::MFA];

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
    public const REPLY_CONTEXTS_SFA = [self::SFA, Constants::AC_PASSWORD_PROTECTED_TRANSPORT];

    /**
     * Contexts which are considered single factor authentication only.
     */
    public const SFA_CONTEXTS = self::REPLY_CONTEXTS_SFA;

    public const ERROR_STATE_ID = 'authswitcher_error_state_id';

    public const ERROR_STATE_STAGE = 'authSwitcher:errorState';

    public const PRIVACY_IDEA_FAIL = 'PrivacyIDEAFail';
}
