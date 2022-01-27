<?php

declare(strict_types=1);

namespace SimpleSAML\Module\authswitcher;

class ProxyHelper
{
    public static function fetchContextFromUpstreamIdp($state)
    {
        if (
            isset($state['saml:RequestedAuthnContext'], $state['saml:RequestedAuthnContext']['AuthnContextClassRef'], $state['saml:sp:AuthnContext'])
        ) {
            $upstreamIdpAuthnContextClassRef = $state['saml:sp:AuthnContext'];
            $requestedAuthnContext = $state['saml:RequestedAuthnContext'];
            $requestedContexts = $requestedAuthnContext['AuthnContextClassRef'];
            if (
                in_array($upstreamIdpAuthnContextClassRef, $requestedContexts, true)
                && !empty($upstreamIdpAuthnContextClassRef)
            ) {
                return $upstreamIdpAuthnContextClassRef;
            }
        }

        return null;
    }
}
