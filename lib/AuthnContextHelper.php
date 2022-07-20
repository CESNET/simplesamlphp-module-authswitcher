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
    public function __construct(
        $password_contexts,
        $mfa_contexts,
        $password_contexts_patterns = [],
        $mfa_contexts_patterns = []
    ) {
        $this->password_contexts = $password_contexts;
        $this->password_contexts_patterns = $password_contexts_patterns;
        $this->mfa_contexts = $mfa_contexts;
        $this->mfa_contexts_patterns = $mfa_contexts_patterns;
        $this->supported_contexts = array_merge($this->mfa_contexts, $this->password_contexts);
        $this->default_requested_contexts = array_merge($this->password_contexts, $this->mfa_contexts);
    }

    public function MFAin($contexts)
    {
        return $this->contextsMatch($contexts, $this->mfa_contexts, $this->mfa_contexts_patterns);
    }

    public function isMFAprefered($supportedRequestedContexts = [])
    {
        return count($supportedRequestedContexts) > 0 && in_array(
            $supportedRequestedContexts[0],
            $this->mfa_contexts,
            true
        );
    }

    public function getSupportedRequestedContexts(
        $usersCapabilities,
        $state,
        $upstreamContext,
        $sfaEntropy,
        $mfaEnforced = false
    ) {
        $requestedContexts = $state['saml:RequestedAuthnContext']['AuthnContextClassRef'] ?? null;
        if (empty($requestedContexts)) {
            Logger::info(
                'authswitcher: no AuthnContext requested, using default: ' . json_encode(
                    $this->default_requested_contexts
                )
            );
            $requestedContexts = $this->default_requested_contexts;
        }
        $supportedRequestedContexts = array_values(array_intersect($requestedContexts, $this->supported_contexts));
        if (!$sfaEntropy) {
            $supportedRequestedContexts = array_diff($supportedRequestedContexts, [Authswitcher::REFEDS_SFA]);
            Logger::info(
                'authswitcher: SFA password entropy level isn\'t satisfied. Remove SFA from SupportedRequestedContext.'
            );
        }

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
            !$this->testComparison(
                $usersCapabilities,
                $supportedRequestedContexts,
                $state['saml:RequestedAuthnContext']['Comparison'] ?? Constants::COMPARISON_EXACT,
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

    public function SFAin($contexts)
    {
        return $this->contextsMatch($contexts, $this->password_contexts, $this->password_contexts_patterns);
    }

    private static function inPatterns($patterns, $contexts)
    {
        foreach ($patterns as $pattern) {
            foreach ($contexts as $context) {
                if (preg_match($pattern, $context)) {
                    return true;
                }
            }
        }
        return false;
    }

    private function contextsMatch($inputContexts, $matchedContexts, $matchedPatterns)
    {
        return !empty(array_intersect($matchedContexts, $inputContexts)) || self::inPatterns(
            $matchedPatterns,
            $inputContexts
        );
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
    private function testComparison(
        $usersCapabilities,
        $supportedRequestedContexts,
        $comparison,
        $upstreamContext = null,
        $mfaEnforced = false
    ) {
        $upstreamMFA = $upstreamContext === null ? false : $this->MFAin([$upstreamContext]);
        $upstreamSFA = $upstreamContext === null ? false : $this->SFAin([$upstreamContext]);

        $requestedSFA = $this->SFAin($supportedRequestedContexts);
        $requestedMFA = $this->MFAin($supportedRequestedContexts);

        $userCanSFA = $this->SFAin($usersCapabilities);
        $userCanMFA = $this->MFAin($usersCapabilities);

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
