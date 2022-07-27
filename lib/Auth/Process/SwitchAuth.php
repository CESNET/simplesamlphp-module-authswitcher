<?php

declare(strict_types=1);

namespace SimpleSAML\Module\authswitcher\Auth\Process;

use Detection\MobileDetect;
use SimpleSAML\Auth\State;
use SimpleSAML\Configuration;
use SimpleSAML\Error\Exception;
use SimpleSAML\Logger;
use SimpleSAML\Module\authswitcher\AuthnContextHelper;
use SimpleSAML\Module\authswitcher\AuthSwitcher;
use SimpleSAML\Module\authswitcher\ContextSettings;
use SimpleSAML\Module\authswitcher\ProxyHelper;
use SimpleSAML\Module\authswitcher\Utils;

class SwitchAuth extends \SimpleSAML\Auth\ProcessingFilter
{
    /* constants */
    private const DEBUG_PREFIX = 'authswitcher:SwitchAuth: ';

    private $type_filter_array = [
        'TOTP' => 'privacyidea:PrivacyideaAuthProc',
        'WebAuthn' => 'privacyidea:PrivacyideaAuthProc',
    ];

    private $mobile_friendly_filters = ['privacyidea:PrivacyideaAuthProc', 'totp:Totp'];

    private $mfa_preferred_privacyidea_fail = false;

    /**
     * Associative array with keys of the form 'module:filter', values are config arrays to be passed to filters.
     */
    private $configs = [];

    /**
     * Second constructor parameter.
     */
    private $reserved;

    private $config;

    private $proxyMode = false;

    private $token_type_attr = 'type';

    private $preferred_filter;

    private $max_user_capability_attr = 'maxUserCapability';

    /**
     * Maximum Authentication assurance.
     */
    private $max_auth = 'https://id.muni.cz/profile/maxAuth';

    private $check_entropy = false;

    private $sfa_alphabet_attr;

    private $sfa_len_attr;

    private $entityID;

    /**
     * @override
     *
     * @param mixed $config
     * @param mixed $reserved
     */
    public function __construct($config, $reserved)
    {
        parent::__construct($config, $reserved);

        $this->config = $config;
        $this->reserved = $reserved;
        $config = isset($config['config']) ? Configuration::loadFromArray(
            $config['config']
        ) : Configuration::getOptionalConfig('module_authswitcher.php');
        $this->type_filter_array = $config->getArray('type_filter_array', $this->type_filter_array);
        $this->mobile_friendly_filters = $config->getArray('mobile_friendly_filters', $this->mobile_friendly_filters);
        $this->token_type_attr = $config->getString('token_type_attr', $this->token_type_attr);
        $this->preferred_filter = $config->getString('preferred_filter', $this->preferred_filter);
        $this->proxyMode = $config->getBoolean('proxy_mode', $this->proxyMode);
        $this->mfa_preferred_privacyidea_fail = $config->getBoolean(
            'mfa_preferred_privacyidea_fail',
            $this->mfa_preferred_privacyidea_fail
        );
        $this->max_user_capability_attr = $config->getString(
            'max_user_capability_attr',
            $this->max_user_capability_attr
        );
        $this->max_auth = $config->getString('max_auth', $this->max_auth);
        $this->sfa_alphabet_attr = $config->getString('sfa_alphabet_attr', $this->sfa_alphabet_attr);
        $this->sfa_len_attr = $config->getString('sfa_len_attr', $this->sfa_len_attr);
        $this->check_entropy = $config->getBoolean('check_entropy', $this->check_entropy);
        $this->entityID = $config->getValue('entityID', null);

        list($this->password_contexts, $this->mfa_contexts, $password_contexts_patterns, $mfa_contexts_patterns) = ContextSettings::parse_config(
            $config
        );

        $this->authnContextHelper = new AuthnContextHelper(
            $this->password_contexts,
            $this->mfa_contexts,
            $password_contexts_patterns,
            $mfa_contexts_patterns
        );
    }

    /**
     * @override
     *
     * @param mixed $state
     */
    public function process(&$state)
    {
        $this->getConfig($this->config);

        $mfaEnforced = Utils::isMFAEnforced($state, $this->entityID);

        $usersCapabilities = $this->getMFAForUid($state);

        self::info('user capabilities: ' . json_encode($usersCapabilities));

        self::setErrorHandling($state);

        if ($this->proxyMode) {
            $upstreamContext = ProxyHelper::fetchContextFromUpstreamIdp($state);
            self::info('upstream context: ' . $upstreamContext);
            ProxyHelper::recoverSPRequestedContexts($state);
        } else {
            $upstreamContext = null;
        }

        $this->supported_requested_contexts = $this->authnContextHelper->getSupportedRequestedContexts(
            $usersCapabilities,
            $state,
            $upstreamContext,
            !$this->check_entropy || $this->checkSfaEntropy($state['Attributes']),
            $mfaEnforced
        );

        self::info('supported requested contexts: ' . json_encode($this->supported_requested_contexts));

        $shouldPerformMFA = !$this->authnContextHelper->MFAin([
            $upstreamContext,
        ]) && ($mfaEnforced || $this->authnContextHelper->isMFAprefered($this->supported_requested_contexts));

        if ($this->mfa_preferred_privacyidea_fail && !empty($state[AuthSwitcher::PRIVACY_IDEA_FAIL]) && $shouldPerformMFA) {
            throw new Exception(self::DEBUG_PREFIX . 'MFA should be performed but connection to privacyidea failed.');
        }

        // switch to MFA if enforced or preferred but not already done if we handle the proxy mode
        $performMFA = $this->authnContextHelper->MFAin($usersCapabilities) && $shouldPerformMFA;

        $maxUserCapability = '';
        if (in_array(AuthSwitcher::REFEDS_MFA, $usersCapabilities, true) || $this->authnContextHelper->MFAin([
            $upstreamContext,
        ])) {
            $maxUserCapability = AuthSwitcher::REFEDS_MFA;
        } elseif (count($usersCapabilities) === 1) {
            $maxUserCapability = $usersCapabilities[0];
        }
        $state['Attributes'][$this->max_user_capability_attr] = [];

        if ($performMFA) {
            $this->performMFA($state, $maxUserCapability);
        } else {
            // SFA or MFA was done at upstream IdP
            $this->setAuthnContext($state, $maxUserCapability, $upstreamContext);
        }
    }

    public function setAuthnContext(&$state, $maxUserCapability, $upstreamContext = null)
    {
        $state[AuthSwitcher::MFA_PERFORMED] = !empty($state[AuthSwitcher::MFA_BEING_PERFORMED]) || $this->authnContextHelper->MFAin([
            $upstreamContext,
        ]);

        if ($maxUserCapability === AuthSwitcher::REFEDS_SFA || ($maxUserCapability === AuthSwitcher::REFEDS_MFA && $state[AuthSwitcher::MFA_PERFORMED])) {
            $state['Attributes'][$this->max_user_capability_attr][] = $this->max_auth;
        }

        $possibleReplies = $state[AuthSwitcher::MFA_PERFORMED] ? array_merge(
            $this->mfa_contexts,
            $this->password_contexts
        ) : $this->password_contexts;
        $possibleReplies = array_values(array_intersect($possibleReplies, $this->supported_requested_contexts));
        if (empty($possibleReplies)) {
            AuthnContextHelper::noAuthnContextResponder($state);
        } else {
            $state['saml:AuthnContextClassRef'] = $possibleReplies[0];
        }
    }

    private function checkSfaEntropy($attributes)
    {
        if (!$this->sfa_len_attr || !$this->sfa_alphabet_attr || !in_array(
            $this->sfa_alphabet_attr,
            $attributes,
            true
        ) || !in_array($this->sfa_len_attr, $attributes, true)) {
            return false;
        }

        if ($attributes[$this->sfa_alphabet_attr] >= 52 && $attributes[$this->sfa_len_attr] >= 12) {
            return true;
        }
        if ($attributes[$this->sfa_alphabet_attr] >= 72 && $attributes[$this->sfa_len_attr] >= 8) {
            return true;
        }

        return false;
    }

    /**
     * Handle NoAuthnContext errors by SAML responses.
     *
     * @param mixed $state
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
     * Log a warning.
     *
     * @param $message
     */
    private function warning($message)
    {
        Logger::warning(self::DEBUG_PREFIX . $message);
    }

    /**
     * Log an info.
     *
     * @param $message
     */
    private function info($message)
    {
        Logger::info(self::DEBUG_PREFIX . $message);
    }

    /**
     * Get configuration parameters from the config array.
     */
    private function getConfig(array $config)
    {
        if (!is_array($config['configs'])) {
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
    }

    private function getMFAForUid($state)
    {
        $result = [];
        if (!empty($state['Attributes'][AuthSwitcher::MFA_TOKENS])) {
            foreach ($state['Attributes'][AuthSwitcher::MFA_TOKENS] as $mfaToken) {
                if (is_string($mfaToken)) {
                    $mfaToken = json_decode($mfaToken, true);
                }

                foreach ($this->type_filter_array as $type => $method) {
                    if ($mfaToken['revoked'] === false && $mfaToken[$this->token_type_attr] === $type) {
                        $result[] = AuthSwitcher::REFEDS_MFA;
                        break;
                    }
                }
                if (!empty($result)) {
                    break;
                }
            }
        }
        $result[] = AuthSwitcher::REFEDS_SFA;

        return $result;
    }

    private function getActiveMethod(&$state)
    {
        $result = [];
        if (!empty($state['Attributes'][AuthSwitcher::MFA_TOKENS])) {
            foreach ($state['Attributes'][AuthSwitcher::MFA_TOKENS] as $mfaToken) {
                if (is_string($mfaToken)) {
                    $mfaToken = json_decode($mfaToken, true);
                }

                foreach ($this->type_filter_array as $type => $filter) {
                    if ($mfaToken['revoked'] === false && $mfaToken[$this->token_type_attr] === $type) {
                        $result[] = $filter;
                    }
                }
            }
        }
        $result = array_values(array_unique($result));
        $detect = new MobileDetect();
        $mobile_pref = $detect->isMobile();
        if ($result === []) {
            return null;
        }
        $state['Attributes']['MFA_FILTERS'] = $result;
        if ($this->preferred_filter !== null && in_array($this->preferred_filter, $result, true)) {
            return $this->preferred_filter;
        }
        if ($mobile_pref) {
            foreach ($result as $filter) {
                if (in_array($filter, $this->mobile_friendly_filters, true)) {
                    return $filter;
                }
            }
        }

        return $result[0];
    }

    /**
     * Perform the appropriate MFA.
     *
     * @param mixed $state
     * @param $maxUserCapability
     */
    private function performMFA(&$state, $maxUserCapability)
    {
        $filter = $this->getActiveMethod($state);

        if (empty($filter)) {
            throw new Exception(
                self::DEBUG_PREFIX . 'Inconsistent data - no MFA methods for a user who should be able to do MFA.'
            );
        }

        if (!isset($this->configs[$filter])) {
            throw new Exception(self::DEBUG_PREFIX . 'Configuration for ' . $filter . ' is missing.');
        }

        if (!isset($state[AuthSwitcher::MFA_BEING_PERFORMED])) {
            $state[AuthSwitcher::MFA_BEING_PERFORMED] = true;
        }
        $this->setAuthnContext($state, $maxUserCapability);
        $state['Attributes']['Config'] = json_encode($this->configs);
        if ($this->reserved === null) {
            $this->reserved = '';
        }
        $state['Attributes']['Reserved'] = $this->reserved;
        $state['Attributes']['MFA_FILTER_INDEX'] = array_search($filter, $state['Attributes']['MFA_FILTERS'], true);
        Utils::runAuthProcFilter($filter, $this->configs[$filter], $state, $this->reserved);
    }
}
