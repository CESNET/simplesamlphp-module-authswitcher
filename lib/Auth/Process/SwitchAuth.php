<?php

namespace SimpleSAML\Module\authswitcher\Auth\Process;

use Detection\MobileDetect;
use SimpleSAML\Auth\State;
use SimpleSAML\Error\Exception;
use SimpleSAML\Logger;
use SimpleSAML\Module\authswitcher\AuthnContextHelper;
use SimpleSAML\Module\authswitcher\AuthSwitcher;
use SimpleSAML\Module\authswitcher\ProxyHelper;
use SimpleSAML\Module\authswitcher\Utils;
use SimpleSAML\Module\saml\Error\NoAuthnContext;

class SwitchAuth extends \SimpleSAML\Auth\ProcessingFilter
{
    /* constants */
    private const DEBUG_PREFIX = 'authswitcher:SwitchAuth: ';

    private const MFA_TOKENS = 'mfaTokens';

    private const TOTP = 'TOTP';

    private const WEBAUTHN = 'WebAuthn';

    private const WEBAUTHN_WEBAUTHN = 'webauthn:WebAuthn';

    private const TOTP_TOTP = 'totp:Totp';

    /* configurable attributes */

    /**
     * Associative array with keys of the form 'module:filter', values are config arrays to be passed to filters.
     */
    private $configs = [];

    /**
     * Second constructor parameter
     */
    private $reserved;

    private $config;

    private $proxyMode = false;

    /**
     * @override
     */
    public function __construct($config, $reserved)
    {
        parent::__construct($config, $reserved);

        $this->config = $config;
        $this->reserved = $reserved;
    }

    /**
     * @override
     */
    public function process(&$state)
    {
        $this->getConfig($this->config);

        $usersCapabilities = $this->getMFAForUid($state);

        self::setErrorHandling($state);

        $upstreamContext = $this->proxyMode ? ProxyHelper::fetchContextFromUpstreamIdp($state) : null;

        $state[AuthSwitcher::SUPPORTED_REQUESTED_CONTEXTS] = AuthnContextHelper::getSupportedRequestedContexts(
            $usersCapabilities,
            $state,
            $upstreamContext
        );

        $performMFA = ! AuthnContextHelper::SFAin($usersCapabilities) || (
            AuthnContextHelper::MFAin($usersCapabilities)
            && AuthnContextHelper::isMFAprefered($state[AuthSwitcher::SUPPORTED_REQUESTED_CONTEXTS])
            && ! AuthnContextHelper::MFAin([$upstreamContext])
        ); // switch to MFA if preferred and not already done if we handle the proxy mode

        if ($performMFA) {
            // MFA
            $this->performMFA($state);
            // setAuthnContext is called in www/switchMfaMethods.php
        } elseif (empty($upstreamContext)) {
            // SFA
            self::setAuthnContext($state);
        }
    }

    public static function setAuthnContext(&$state)
    {
        $possibleReplies = self::wasMFAPerformed(
            $state
        ) ? AuthSwitcher::REPLY_CONTEXTS_MFA : AuthSwitcher::REPLY_CONTEXTS_SFA;
        $possibleReplies = array_values(
            array_intersect($possibleReplies, $state[AuthSwitcher::SUPPORTED_REQUESTED_CONTEXTS])
        );
        if (empty($possibleReplies)) {
            AuthnContextHelper::noAuthnContextResponder($state);
        } else {
            $state['saml:AuthnContextClassRef'] = $possibleReplies[0];
        }
    }

    /**
     * Handle NoAuthnContext errors by SAML responses.
     */
    private static function setErrorHandling(&$state)
    {
        $error_state = State::cloneState($state);
        unset($error_state[State::EXCEPTION_HANDLER_URL]);
        $error_state[State::EXCEPTION_HANDLER_FUNC]
            = ['\\SimpleSAML\\Module\\saml\\IdP\\SAML2', 'handleAuthError'];
        $state[AuthSwitcher::ERROR_STATE_ID] = State::saveState($error_state, Authswitcher::ERROR_STATE_STAGE);
    }

    /**
     * Check if the MFA auth proc filters (which were run) finished successfully. If everything is configured correctly,
     * this should not throw an exception.
     */
    private static function wasMFAPerformed($state)
    {
        return ! empty($state[AuthSwitcher::MFA_BEING_PERFORMED]);
    }

    /**
     * Log a warning.
     *
     * @param $message
     */
    private function warning($message)
    {
        Logger::warning(self::DEBUG_PREFIX . $message);
    }

    /**
     * Get configuration parameters from the config array.
     */
    private function getConfig(array $config)
    {
        if (! is_array($config['configs'])) {
            throw new Exception(self::DEBUG_PREFIX . 'Configurations are missing.');
        }
        $filterModules = array_keys($config['configs']);
        $invalidModules = Utils::areFilterModulesEnabled($filterModules);
        if ($invalidModules !== true) {
            $this->warning(
                'Some modules (' . implode(',', $invalidModules) . ')'
                . ' in the configuration are missing or disabled.'
            );
        }
        $this->configs = $config['configs'];
        if (isset($config['proxy_mode'])) {
            $this->proxyMode = $config['proxy_mode'];
        }
    }

    private function getMFAForUid($state)
    {
        $result = [];
        if (! empty($state['Attributes'][self::MFA_TOKENS])) {
            foreach ($state['Attributes'][self::MFA_TOKENS] as $mfaToken) {
                $token = json_decode($mfaToken, true);
                if ($token['revoked'] === false) {
                    $result[] = AuthSwitcher::MFA;
                    break;
                }
            }
        }
        if (empty($state['Attributes']['mfaEnforced'])) {
            $result[] = AuthSwitcher::SFA;
        }
        return $result;
    }

    private function getActiveMethod(&$state)
    {
        $result = [];
        if (! empty($state['Attributes'][self::MFA_TOKENS])) {
            foreach ($state['Attributes'][self::MFA_TOKENS] as $mfaToken) {
                $token = json_decode($mfaToken, true);
                if ($token['revoked'] === false && $token['type'] === self::TOTP) {
                    $result[] = self::TOTP_TOTP;
                } elseif ($token['revoked'] === false && $token['type'] === self::WEBAUTHN) {
                    $result[] = self::WEBAUTHN_WEBAUTHN;
                }
            }
        }
        $result = array_values(array_unique($result));
        $detect = new MobileDetect();
        $totpPref = $detect->isMobile();
        if ($result === []) {
            return null;
        }
        $state['Attributes']['MFA_METHODS'] = $result;
        if ($totpPref && in_array(self::TOTP_TOTP, $result, true) && in_array(self::WEBAUTHN_WEBAUTHN, $result, true)) {
            return self::TOTP_TOTP;
        } elseif (
            ! $totpPref
            && in_array(self::TOTP_TOTP, $result, true) && in_array(self::WEBAUTHN_WEBAUTHN, $result, true)
        ) {
            return self::WEBAUTHN_WEBAUTHN;
        }
        return $result[0];
    }

    /**
     * Perform the appropriate MFA.
     *
     * @param $state
     */
    private function performMFA(&$state)
    {
        $method = $this->getActiveMethod($state);

        if (empty($method)) {
            throw new Exception(
                self::DEBUG_PREFIX
                . 'Inconsistent data - no MFA methods for a user who should be able to do MFA.'
            );
        }

        if (! isset($this->configs[$method])) {
            throw new Exception(self::DEBUG_PREFIX . 'Configuration for ' . $method . ' is missing.');
        }

        if (! isset($state[AuthSwitcher::MFA_BEING_PERFORMED])) {
            $state[AuthSwitcher::MFA_BEING_PERFORMED] = true;
        }
        $state['Attributes']['Config'] = json_encode($this->configs);
        if ($this->reserved === null) {
            $this->reserved = '';
        }
        $state['Attributes']['Reserved'] = $this->reserved;
        $state['Attributes']['MFA_FILTER_INDEX'] = array_search($method, $state['Attributes']['MFA_METHODS'], true);
        Utils::runAuthProcFilter($method, $this->configs[$method], $state, $this->reserved);
    }
}
