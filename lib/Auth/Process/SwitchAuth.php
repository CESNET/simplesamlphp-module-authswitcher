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

    private const WEBAUTHN = 'webauthn';

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

    /**
     * State with exception handler set.
     */
    private $errorState;

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

        $this->errorState = $state;
        unset($this->errorState[State::EXCEPTION_HANDLER_URL]);
        $this->errorState[State::EXCEPTION_HANDLER_FUNC]
            = ['\\SimpleSAML\\Module\\saml\\IdP\\SAML2', 'handleAuthError'];

        if (
            isset($state['saml:RequestedAuthnContext'])
            && isset($state['saml:RequestedAuthnContext']['AuthnContextClassRef'])
        ) :
            $requestedAuthnContext = $state['saml:RequestedAuthnContext'];
            $requestedContexts = $requestedAuthnContext['AuthnContextClassRef'];
            $supportedRequestedContexts = array_intersect(
                $requestedAuthnContext['AuthnContextClassRef'],
                AuthSwitcher::SUPPORTED
            );

            if (! empty($requestedContexts) && empty($supportedRequestedContexts)) {
                State::throwException($this->errorState, new NoAuthnContext(AuthSwitcher::SAML2_STATUS_REQUESTER));
                exit;
            }

            $this->requestedSFA = self::SFAin($supportedRequestedContexts);
            $this->requestedMFA = self::MFAin($supportedRequestedContexts);
            if (! empty($supportedRequestedContexts)) {
                // check for unsatisfiable combinations
                $this->testAuthnContextComparison($requestedAuthnContext['Comparison']);
                // switch to MFA if prefered
                if ($this->userCanMFA && self::isMFAprefered($supportedRequestedContexts)) {
                    $performMFA = true;
                }
            }
        endif;
        if ($performMFA) {
            $this->performMFA($state);
        }
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
    private function testAuthnContextComparison($comparison)
    {
        switch ($comparison) {
            case 'better':
                if (! $this->userCanMFA || ! $this->requestedSFA) {
                    $this->noAuthnContextResponder();
                }
                break;
            case 'minimum':
                if (! $this->userCanMFA && $this->requestedMFA) {
                    $this->noAuthnContextResponder();
                }
                break;
            case 'maximum':
                if (! $this->userCanSFA && $this->requestedSFA) {
                    $this->noAuthnContextResponder();
                }
                break;
            case 'exact':
            default:
                if (! $this->userCanMFA && ! $this->requestedSFA) {
                    $this->noAuthnContextResponder();
                }
                if (! $this->userCanSFA && ! $this->requestedMFA) {
                    $this->noAuthnContextResponder();
                }
                break;
        }
    }

    /**
     * @throws NoAuthnContext
     */
    private function noAuthnContextResponder()
    {
        State::throwException($this->errorState, new NoAuthnContext(AuthSwitcher::SAML2_STATUS_RESPONDER));
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
                    return $result;
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
        $result = array_unique($result);
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
