<?php

namespace SimpleSAML\Module\authswitcher;

use SimpleSAML\Auth\State;
use SimpleSAML\Module\saml\Error\NoAuthnContext;

/**
 * Authentication context handling.
 */
class AuthnContextHelper
{
    public static function MFAin($contexts)
    {
        return in_array(AuthSwitcher::MFA, $contexts, true);
    }

    public static function isMFAprefered($supportedRequestedContexts = [])
    {
        return count($supportedRequestedContexts) > 0 && $supportedRequestedContexts[0] === AuthSwitcher::MFA;
    }

    public static function getSupportedRequestedContexts($usersCapabilities, $state, $upstreamContext)
    {
        $requestedContexts = $state['saml:RequestedAuthnContext']['AuthnContextClassRef'] ?? null;
        if (empty($requestedContexts)) {
            Logger::info('authswitcher: no AuthnContext requested, using default: ' . json_encode(AuthSwitcher::DEFAULT_REQUESTED_CONTEXTS));
            return AuthSwitcher::DEFAULT_REQUESTED_CONTEXTS;
        }
        $supportedRequestedContexts = array_values(array_intersect($requestedContexts, AuthSwitcher::SUPPORTED));

        if (
            ! empty($requestedContexts) // sp has requested something
            && empty($supportedRequestedContexts) // nothing of that is supported by authswitcher
            && empty($upstreamContext) // it was neither filled from upstream IdP
        ) {
            Logger::info('authswitcher: no requested AuthnContext is supported: ' . json_encode($requestedContexts));
            self::noAuthnContextRequester($state);
        }

        // check for unsatisfiable combinations
        if (
            ! self::testComparison(
                $usersCapabilities,
                $supportedRequestedContexts,
                $state['saml:RequestedAuthnContext']['Comparison'],
                $upstreamContext
            )
        ) {
            Logger::info('authswitcher: no requested AuthnContext can be fulfilled: ' . json_encode($requestedContexts));
            self::noAuthnContextResponder($state);
        }

        return $supportedRequestedContexts;
    }

    public static function noAuthnContextResponder($state)
    {
        self::noAuthnContext($state, AuthSwitcher::SAML2_STATUS_RESPONDER);
    }

    public static function SFAin($contexts)
    {
        return in_array(AuthSwitcher::SFA, $contexts, true)
            || in_array(AuthSwitcher::PASS, $contexts, true);
    }

    /**
     * If the Comparison attribute is set to “better”, “minimum”, or “maximum”, the method of authentication
     * must be stronger than, at least as strong as, or no stronger than one of the specified authentication classes.
     */
    private static function testComparison(
        $usersCapabilities,
        $supportedRequestedContexts,
        $comparison,
        $upstreamContext = null
    ) {
        $upstreamMFA = $upstreamContext === null ? false : self::MFAin([$upstreamContext]);
        $upstreamSFA = $upstreamContext === null ? false : self::SFAin([$upstreamContext]);

        $requestedSFA = self::SFAin($supportedRequestedContexts);
        $requestedMFA = self::MFAin($supportedRequestedContexts);

        $userCanSFA = self::SFAin($usersCapabilities);
        $userCanMFA = self::MFAin($usersCapabilities);

        switch ($comparison) {
            case 'better':
                if (! ($userCanMFA || $upstreamMFA) || ! $requestedSFA) {
                    return false;
                }
                break;
            case 'minimum':
                if (! ($userCanMFA || $upstreamMFA) && $requestedMFA) {
                    return false;
                }
                break;
            case 'maximum':
                if (! ($userCanSFA || $upstreamSFA) && $requestedSFA) {
                    return false;
                }
                break;
            case 'exact':
            default:
                if (! ($userCanMFA || $upstreamMFA) && ! $requestedSFA) {
                    return false;
                }
                if (! ($userCanSFA || $upstreamSFA) && ! $requestedMFA) {
                    return false;
                }
                break;
        }

        return true;
    }

    private static function noAuthnContext($state, $status)
    {
        State::throwException(
            State::loadState($state[AuthSwitcher::ERROR_STATE_ID], AuthSwitcher::ERROR_STATE_STAGE),
            new NoAuthnContext($status)
        );
        exit;
    }

    private static function noAuthnContextRequester($state)
    {
        self::noAuthnContext($state, AuthSwitcher::SAML2_STATUS_REQUESTER);
    }
}
