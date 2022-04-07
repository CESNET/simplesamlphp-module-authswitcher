<?php

declare(strict_types=1);

namespace SimpleSAML\Module\authswitcher;

use SAML2\Constants;
use SimpleSAML\Auth\State;
use SimpleSAML\Logger;
use SimpleSAML\Module\saml\Error\NoAuthnContext;

/**
 * Authentication context handling.
 */
class AuthnContextHelper
{
    public static function MFAin($contexts)
    {
        return array_intersect(AuthSwitcher::MFA_CONTEXTS, $contexts);
    }

    public static function isMFAprefered($supportedRequestedContexts = [])
    {
        return count($supportedRequestedContexts) > 0 && in_array(
            $supportedRequestedContexts[0],
            AuthSwitcher::MFA_CONTEXTS,
            true
        );
    }

    public static function getSupportedRequestedContexts(
        $usersCapabilities,
        $state,
        $upstreamContext,
        $mfaEnforced = false
    ) {
        $requestedContexts = $state['saml:RequestedAuthnContext']['AuthnContextClassRef'] ?? null;
        if (empty($requestedContexts)) {
            Logger::info(
                'authswitcher: no AuthnContext requested, using default: ' . json_encode(
                    AuthSwitcher::DEFAULT_REQUESTED_CONTEXTS
                )
            );

            return AuthSwitcher::DEFAULT_REQUESTED_CONTEXTS;
        }
        $supportedRequestedContexts = array_values(array_intersect($requestedContexts, AuthSwitcher::SUPPORTED));

        if (
            !empty($requestedContexts) // sp has requested something
            && empty($supportedRequestedContexts) // nothing of that is supported by authswitcher
            && empty($upstreamContext) // it was neither filled from upstream IdP
        ) {
            Logger::info('authswitcher: no requested AuthnContext is supported: ' . json_encode($requestedContexts));
            self::noAuthnContextRequester($state);
        }

        // check for unsatisfiable combinations
        if (
            !self::testComparison(
                $usersCapabilities,
                $supportedRequestedContexts,
                $state['saml:RequestedAuthnContext']['Comparison'],
                $upstreamContext,
                $mfaEnforced
            )
        ) {
            Logger::info(
                'authswitcher: no requested AuthnContext can be fulfilled: ' . json_encode($requestedContexts)
            );
            self::noAuthnContextResponder($state);
        }

        return $supportedRequestedContexts;
    }

    public static function noAuthnContextResponder($state)
    {
        self::noAuthnContext($state, Constants::STATUS_RESPONDER);
    }

    public static function SFAin($contexts)
    {
        foreach (AuthSwitcher::SFA_CONTEXTS as $sfa_context) {
            if (in_array($sfa_context, $contexts, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * If the Comparison attribute is set to “better”, “minimum”, or “maximum”, the method of authentication
     * must be stronger than, at least as strong as, or no stronger than one of the specified authentication classes.
     *
     * @param mixed      $usersCapabilities
     * @param mixed      $supportedRequestedContexts
     * @param mixed      $comparison
     * @param mixed|null $upstreamContext
     * @param mixed      $mfaEnforced
     */
    private static function testComparison(
        $usersCapabilities,
        $supportedRequestedContexts,
        $comparison,
        $upstreamContext = null,
        $mfaEnforced = false
    ) {
        $upstreamMFA = null === $upstreamContext ? false : self::MFAin([$upstreamContext]);
        $upstreamSFA = null === $upstreamContext ? false : self::SFAin([$upstreamContext]);

        $requestedSFA = self::SFAin($supportedRequestedContexts);
        $requestedMFA = self::MFAin($supportedRequestedContexts);

        $userCanSFA = self::SFAin($usersCapabilities);
        $userCanMFA = self::MFAin($usersCapabilities);

        switch ($comparison) {
            case Constants::COMPARISON_BETTER:
                if (!($userCanMFA || $upstreamMFA) || !$requestedSFA) {
                    return false;
                }
                break;
            case Constants::COMPARISON_MINIMUM:
                if (!($userCanMFA || $upstreamMFA) && $requestedMFA) {
                    return false;
                }
                break;
            case Constants::COMPARISON_MAXIMUM:
                if ($mfaEnforced && $requestedSFA) {
                    return false;
                }
                break;
            case Constants::COMPARISON_EXACT:
            default:
                if (!($userCanMFA || $upstreamMFA) && !$requestedSFA) {
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
        self::noAuthnContext($state, Constants::STATUS_REQUESTER);
    }
}
