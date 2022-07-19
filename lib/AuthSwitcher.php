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
     * Key into state array for MFA being performed.
     */
    public const MFA_BEING_PERFORMED = 'authswitcher_mfa_being_performed';

    /**
     * Key into state array for MFA was done.
     */
    public const MFA_PERFORMED = 'authswitcher_mfa_performed';

    /**
     * REFEDS profile for SFA.
     */
    public const REFEDS_SFA = 'https://refeds.org/profile/sfa';

    /**
     * REFEDS profile for MFA.
     */
    public const REFEDS_MFA = 'https://refeds.org/profile/mfa';

    /**
     * Microsoft authentication context for MFA.
     */
    public const MS_MFA = 'http://schemas.microsoft.com/claims/multipleauthn';

    /**
     * Contexts trusted as multifactor authentication, in the order of preference (for replies).
     */
    public const MFA_CONTEXTS = [self::REFEDS_MFA, self::MS_MFA];

    /**
     * Contexts trusted as password authentication, in the order of preference (for replies).
     */
    public const PASSWORD_CONTEXTS = [self::REFEDS_SFA, Constants::AC_PASSWORD_PROTECTED_TRANSPORT];

    public const ERROR_STATE_ID = 'authswitcher_error_state_id';

    public const ERROR_STATE_STAGE = 'authSwitcher:errorState';

    public const PRIVACY_IDEA_FAIL = 'PrivacyIDEAFail';

    public const SP_REQUESTED_CONTEXTS = 'AUTHSWITCHER_SP_REQUESTED_CONTEXTS';
}
