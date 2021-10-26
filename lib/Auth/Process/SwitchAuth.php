<?php

namespace SimpleSAML\Module\authswitcher\Auth\Process;

use Detection\MobileDetect;
use SimpleSAML\Auth\State;
use SimpleSAML\Error\Exception;
use SimpleSAML\Logger;
use SimpleSAML\Module\authswitcher\AuthSwitcher;
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

    private $userCanSFA;

    private $userCanMFA;

    private $requestedSFA;

    private $requestedMFA;

    private $config;

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

        // pass requested => perform SFA and return pass
        // SFA requested => perform SFA and return SFA
        // MFA requested => perform MFA and return MFA

        $usersCapabilities = $this->getMFAForUid($state);
        assert(! empty($usersCapabilities));
        $this->userCanSFA = self::SFAin($usersCapabilities);
        $this->userCanMFA = self::MFAin($usersCapabilities);
        $performMFA = ! $this->userCanSFA;
        // only SFA => SFA, inactive MFA (both) => SFA (if MFA not preferred by SP), active MFA => MFA
        // i.e. when possible, SFA is prefered

        $error_state = State::cloneState($state);
        unset($error_state[State::EXCEPTION_HANDLER_URL]);
        $error_state[State::EXCEPTION_HANDLER_FUNC]
            = ['\\SimpleSAML\\Module\\saml\\IdP\\SAML2', 'handleAuthError'];
        $state[AuthSwitcher::ERROR_STATE_ID] = State::saveState($error_state, Authswitcher::ERROR_STATE_STAGE);

        if (
            isset($state['saml:RequestedAuthnContext'])
            && isset($state['saml:RequestedAuthnContext']['AuthnContextClassRef'])
        ) {
            $requestedAuthnContext = $state['saml:RequestedAuthnContext'];
            $requestedContexts = $requestedAuthnContext['AuthnContextClassRef'];
            $supportedRequestedContexts = array_values(array_intersect(
                $requestedAuthnContext['AuthnContextClassRef'],
                AuthSwitcher::SUPPORTED
            ));

            if (! empty($requestedContexts) && empty($supportedRequestedContexts)) {
                State::throwException(
                    State::loadState($state[AuthSwitcher::ERROR_STATE_ID], AuthSwitcher::ERROR_STATE_STAGE),
                    new NoAuthnContext(AuthSwitcher::SAML2_STATUS_REQUESTER)
                );
            }

            $this->requestedSFA = self::SFAin($supportedRequestedContexts);
            $this->requestedMFA = self::MFAin($supportedRequestedContexts);
            if (! empty($supportedRequestedContexts)) {
                // check for unsatisfiable combinations
                $this->testAuthnContextComparison($requestedAuthnContext['Comparison'], $state);
                // switch to MFA if prefered
                if ($this->userCanMFA && self::isMFAprefered($supportedRequestedContexts)) {
                    $performMFA = true;
                }
            }
        } else {
            $supportedRequestedContexts = AuthSwitcher::DEFAULT_REQUESTED_CONTEXTS;
        }
        $state[AuthSwitcher::SUPPORTED_REQUESTED_CONTEXTS] = $supportedRequestedContexts;
        if ($performMFA) {
            if (
                isset($state['saml:sp:State']['saml:sp:AuthnContext']) &&
                in_array(AuthSwitcher::MFA, $state['saml:sp:State']['saml:sp:AuthnContext'], true)
            ) {
                $state[AuthSwitcher::MFA_BEING_PERFORMED] = true;
                self::setAuthnContext($state);
            } else {
                // MFA
                $this->performMFA($state);
                // setAuthnContext is called in www/switchMfaMethods.php
            }
        } else {
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
            self::noAuthnContextResponder($state);
        } else {
            $state['saml:AuthnContextClassRef'] = $possibleReplies[0];
        }
    }

    /**
     * Check if the MFA auth proc filters (which were run) finished successfully. If everything is configured correctly,
     * this should not throw an exception.
     */
    private static function wasMFAPerformed($state)
    {
        return ! empty($state[AuthSwitcher::MFA_BEING_PERFORMED]);
    }

    /* logging */

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
            $this->warning('Some modules (' . implode(',', $invalidModules) . ')'
                . ' in the configuration are missing or disabled.');
        }
        $this->configs = $config['configs'];
    }

    private static function SFAin($contexts)
    {
        return in_array(AuthSwitcher::SFA, $contexts, true)
            || in_array(AuthSwitcher::PASS, $contexts, true);
    }

    private static function MFAin($contexts)
    {
        return in_array(AuthSwitcher::MFA, $contexts, true);
    }

    /**
     * If the Comparison attribute is set to “better”, “minimum”, or “maximum”, the method of authentication
     * must be stronger than, at least as strong as, or no stronger than one of the specified authentication classes.
     *
     * @throws NoAuthnContext
     */
    private function testAuthnContextComparison($comparison, $state)
    {
        switch ($comparison) {
            case 'better':
                if (! $this->userCanMFA || ! $this->requestedSFA) {
                    self::noAuthnContextResponder($state);
                }
                break;
            case 'minimum':
                if (! $this->userCanMFA && $this->requestedMFA) {
                    self::noAuthnContextResponder($state);
                }
                break;
            case 'maximum':
                if (! $this->userCanSFA && $this->requestedSFA) {
                    self::noAuthnContextResponder($state);
                }
                break;
            case 'exact':
            default:
                if (! $this->userCanMFA && ! $this->requestedSFA) {
                    self::noAuthnContextResponder($state);
                }
                if (! $this->userCanSFA && ! $this->requestedMFA) {
                    self::noAuthnContextResponder($state);
                }
                break;
        }
    }

    /**
     * @throws NoAuthnContext
     */
    private static function noAuthnContextResponder($state)
    {
        State::throwException(
            State::loadState($state[AuthSwitcher::ERROR_STATE_ID], Authswitcher::ERROR_STATE_STAGE),
            new NoAuthnContext(AuthSwitcher::SAML2_STATUS_RESPONDER)
        );
        exit;
    }

    private static function isMFAprefered($supportedRequestedContexts)
    {
        // assert($supportedRequestedContexts is a nonempty subset of AuthSwitcher::SUPPORTED)
        return $supportedRequestedContexts[0] === AuthSwitcher::MFA;
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
            throw new Exception(self::DEBUG_PREFIX
                . 'Inconsistent data - no MFA methods for a user who should be able to do MFA.');
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
