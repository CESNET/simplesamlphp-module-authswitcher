<?php

declare(strict_types=1);

namespace SimpleSAML\Module\authswitcher;

use SimpleSAML\Logger;

class ProxyHelper
{
    /**
     * Return context from IdP if replied with valid one.
     */
    public static function fetchContextFromUpstreamIdp($state)
    {
        if (isset($state['saml:RequestedAuthnContext']['AuthnContextClassRef']) && isset($state['saml:sp:AuthnContext'])) {
            $upstreamIdpAuthnContextClassRef = $state['saml:sp:AuthnContext'];
            $requestedContexts = $state['saml:RequestedAuthnContext']['AuthnContextClassRef'];
            if (
                in_array($upstreamIdpAuthnContextClassRef, $requestedContexts, true)
                && !empty($upstreamIdpAuthnContextClassRef)
            ) {
                return $upstreamIdpAuthnContextClassRef;
            }
        }

        // IdP returned a context which was not requested, ignore it
        return null;
    }

    public static function recoverSPRequestedContexts(&$state)
    {
        if (isset($state[AuthSwitcher::SP_REQUESTED_CONTEXTS])) {
            $state['saml:RequestedAuthnContext']['AuthnContextClassRef'] = $state[AuthSwitcher::SP_REQUESTED_CONTEXTS];
        } else {
            Logger::error('authswitcher: running in proxy mode but setUpstreamRequestedAuthnContext was not called.');
        }
    }
}
