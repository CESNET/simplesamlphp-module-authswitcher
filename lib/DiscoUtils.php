<?php

declare(strict_types=1);

namespace SimpleSAML\Module\authswitcher;

use SimpleSAML\Configuration;
use SimpleSAML\Logger;

/**
 * Utility class for handling AuthnContextClassRef on a proxy.
 *
 * The setUpstreamRequestedAuthnContext method needs to be called from a disco page (before the user is sent to upstream
 * IdP).
 */
class DiscoUtils
{
    private const DEBUG_PREFIX = 'authswitcher:DiscoUtils: ';

    /**
     * Store requested AuthnContextClassRef from SP and modify them before sending to upstream IdP.
     *
     * Contexts for password and MFA authentication are added to non-empty requested contexts so that upstream IdP does
     * not fail with an error.
     *
     * @param array $state global state (request)
     */
    public static function setUpstreamRequestedAuthnContext(array &$state)
    {
        $config = Configuration::getOptionalConfig('module_authswitcher.php');
        list($password_contexts, $mfa_contexts, $password_contexts_patterns, $mfa_contexts_patterns) = ContextSettings::parse_config(
            $config
        );
        $authnContextHelper = new AuthnContextHelper(
            $password_contexts,
            $mfa_contexts,
            $password_contexts_patterns,
            $mfa_contexts_patterns
        );

        $spRequestedContexts = $state['saml:RequestedAuthnContext']['AuthnContextClassRef'] ?? [];

        // store originally requested contexts for correct handling in SwitchAuth
        $state[AuthSwitcher::SP_REQUESTED_CONTEXTS] = $spRequestedContexts;

        $upstreamRequestedContexts = [];
        if (empty($spRequestedContexts)) {
            Logger::debug(self::DEBUG_PREFIX . 'No AuthnContextClassRef requested, not sending any to upstream IdP.');
        } elseif ($authnContextHelper->MFAin($spRequestedContexts) && !$authnContextHelper->SFAin($spRequestedContexts)) {
            Logger::debug(self::DEBUG_PREFIX . 'SP requires MFA, will prefer MFA at upstream IdP.');
            $upstreamRequestedContexts = array_values(
                array_unique(array_merge($mfa_contexts, $spRequestedContexts, $password_contexts))
            );
        } elseif (
            $authnContextHelper->MFAin($spRequestedContexts)
            && self::getMinIndex($spRequestedContexts, $mfa_contexts) < self::getMinIndex(
                $spRequestedContexts,
                $password_contexts
            )
        ) {
            Logger::debug(self::DEBUG_PREFIX . 'SP prefers MFA, will prefer MFA at upstream IdP.');
            $upstreamRequestedContexts = array_values(
                array_unique(array_merge($mfa_contexts, $spRequestedContexts, $password_contexts))
            );
        } else {
            Logger::debug(self::DEBUG_PREFIX . 'SP does not prefer MFA, will prefer SFA at upstream IdP.');
            $upstreamRequestedContexts = array_values(
                array_unique(array_merge($spRequestedContexts, $password_contexts, $mfa_contexts))
            );
        }
        if (!empty($upstreamRequestedContexts)) {
            Logger::debug(
                self::DEBUG_PREFIX . 'AuthnContextClassRefs sent to upstream IdP: ' . join(
                    ',',
                    $upstreamRequestedContexts
                )
            );
            $state['saml:RequestedAuthnContext']['AuthnContextClassRef'] = $upstreamRequestedContexts;
        }
    }

    /**
     * Returns first index in arr1 of any element from arr2
     */
    private static function getMinIndex($arr1, $arr2)
    {
        return min(array_keys(array_intersect($arr1, $arr2)));
    }
}
