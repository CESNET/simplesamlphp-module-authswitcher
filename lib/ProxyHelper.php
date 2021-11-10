<?php

namespace SimpleSAML\Module\authswitcher;

class ProxyHelper
{
    public static function fetchContextFromUpstreamIdp($state)
    {
        if (
            isset($state['saml:RequestedAuthnContext'])
            && isset($state['saml:RequestedAuthnContext']['AuthnContextClassRef'])
            && isset($state['saml:sp:AuthnContext'])
        ) {
            $upstreamIdpAuthnContextClassRef = $state['saml:sp:AuthnContext'];
            $requestedAuthnContext = $state['saml:RequestedAuthnContext'];
            $requestedContexts = $requestedAuthnContext['AuthnContextClassRef'];
            if (
                in_array($upstreamIdpAuthnContextClassRef, $requestedContexts, true)
                && ! empty($upstreamIdpAuthnContextClassRef)
            ) {
                return $upstreamIdpAuthnContextClassRef;
            }
        }
        return null;
    }
}
