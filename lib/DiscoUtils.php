<?php

declare(strict_types=1);

namespace SimpleSAML\Module\authswitcher;

use SimpleSAML\Logger;

/**
 * Utility class for handling AuthnContextClassRef on a proxy.
 *
 * The setUpstreamRequestedAuthnContext method needs to be called from a disco page (before the user is sent to upstream
 * IdP).
 */
class DiscoUtils
{
    private static const DEBUG_PREFIX = 'AuthSwitcher: ';

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
        $spRequestedContexts = $state['saml:RequestedAuthnContext']['AuthnContextClassRef'] ?? [];

        // store originally requested contexts for correct handling in SwitchAuth
        $state[AuthSwitcher::SP_REQUESTED_CONTEXTS] = $spRequestedContexts;

        $upstreamRequestedContexts = [];
        if (empty($spRequestedContexts)) {
            Logger::debug(self::DEBUG_PREFIX . 'No AuthnContextClassRef requested, not sending any to upstream IdP.');
        } elseif (!empty(array_diff(AuthSwitcher::REPLY_CONTEXTS_MFA, $spRequestedContexts))) {
            Logger::debug(self::DEBUG_PREFIX . 'SP requested MFA, will prefer MFA at upstream IdP.');
            $upstreamRequestedContexts = array_values(
                array_unique(array_merge(
                    AuthSwitcher::REPLY_CONTEXTS_MFA,
                    $spRequestedContexts,
                    AuthSwitcher::REPLY_CONTEXTS_SFA
                ))
            );
        } else {
            Logger::debug(self::DEBUG_PREFIX . 'SP did not request MFA, will prefer SFA at upstream IdP.');
            $upstreamRequestedContexts = array_values(
                array_unique(array_merge(
                    $spRequestedContexts,
                    AuthSwitcher::REPLY_CONTEXTS_SFA,
                    AuthSwitcher::REPLY_CONTEXTS_MFA
                ))
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
}
