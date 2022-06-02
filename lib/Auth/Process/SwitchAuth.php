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
use SimpleSAML\Module\authswitcher\ProxyHelper;
use SimpleSAML\Module\authswitcher\Utils;

class SwitchAuth extends \SimpleSAML\Auth\ProcessingFilter
{
    /* constants */
    private const DEBUG_PREFIX = 'authswitcher:SwitchAuth: ';

    private const MFA_TOKENS = 'mfaTokens';

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

    /**
     * Whether MFA is enforced for the current user.
     */
    private $mfa_enforced;

    private $check_entropy = false;

    private $sfa_alphabet_attr;

    private $sfa_len_attr;

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
        $config = Configuration::loadFromArray($config['config']);
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
    }

    /**
     * @override
     *
     * @param mixed $state
     */
    public function process(&$state)
    {
        $this->mfa_enforced = !empty($state['Attributes']['mfaEnforced']);

        $this->getConfig($this->config);

        $usersCapabilities = $this->getMFAForUid($state);

        self::info('user capabilities: ' . json_encode($usersCapabilities));

        self::setErrorHandling($state);

        if ($this->proxyMode) {
            $upstreamContext = ProxyHelper::fetchContextFromUpstreamIdp($state);
            self::info('upstream context: ' . $upstreamContext);
        } else {
            $upstreamContext = null;
        }

        $state[AuthSwitcher::SUPPORTED_REQUESTED_CONTEXTS] = AuthnContextHelper::getSupportedRequestedContexts(
            $usersCapabilities,
            $state,
            $upstreamContext,
            !$this->check_entropy || $this->checkSfaEntropy($state['Attributes']),
            $this->mfa_enforced
        );

        self::info('supported requested contexts: ' . json_encode($state[AuthSwitcher::SUPPORTED_REQUESTED_CONTEXTS]));

        $shouldPerformMFA = !AuthnContextHelper::MFAin([
            $upstreamContext,
        ]) && ($this->mfa_enforced || AuthnContextHelper::isMFAprefered(
            $state[AuthSwitcher::SUPPORTED_REQUESTED_CONTEXTS]
        ));

        if ($this->mfa_preferred_privacyidea_fail && !empty($state[AuthSwitcher::PRIVACY_IDEA_FAIL]) && $shouldPerformMFA) {
            throw new Exception(self::DEBUG_PREFIX . 'MFA should be performed but connection to privacyidea failed.');
        }

        // switch to MFA if enforced or preferred but not already done if we handle the proxy mode
        $performMFA = AuthnContextHelper::MFAin($usersCapabilities) && $shouldPerformMFA;

        $maxUserCapability = '';
        if (in_array(AuthSwitcher::MFA, $usersCapabilities, true) || AuthnContextHelper::MFAin([$upstreamContext])) {
            $maxUserCapability = AuthSwitcher::MFA;
        } elseif (1 === count($usersCapabilities)) {
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
        $mfaPerformed = Utils::wasMFAPerformed($state, $upstreamContext);

        if (AuthSwitcher::SFA === $maxUserCapability || (AuthSwitcher::MFA === $maxUserCapability && $mfaPerformed)) {
            $state['Attributes'][$this->max_user_capability_attr][] = $this->max_auth;
        }

        $possibleReplies = $mfaPerformed ? array_merge(
            AuthSwitcher::REPLY_CONTEXTS_MFA,
            AuthSwitcher::REPLY_CONTEXTS_SFA
        ) : AuthSwitcher::REPLY_CONTEXTS_SFA;
        $possibleReplies = array_values(
            array_intersect($possibleReplies, $state[AuthSwitcher::SUPPORTED_REQUESTED_CONTEXTS])
        );
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
        if (true !== $invalidModules) {
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
        if (!empty($state['Attributes'][self::MFA_TOKENS])) {
            foreach ($state['Attributes'][self::MFA_TOKENS] as $mfaToken) {
                if (is_string($mfaToken)) {
                    $mfaToken = json_decode($mfaToken, true);
                }

                foreach ($this->type_filter_array as $type => $method) {
                    if (false === $mfaToken['revoked'] && $mfaToken[$this->token_type_attr] === $type) {
                        $result[] = AuthSwitcher::MFA;
                        break;
                    }
                }
                if (!empty($result)) {
                    break;
                }
            }
        }
        $result[] = AuthSwitcher::SFA;

        return $result;
    }

    private function getActiveMethod(&$state)
    {
        $result = [];
        if (!empty($state['Attributes'][self::MFA_TOKENS])) {
            foreach ($state['Attributes'][self::MFA_TOKENS] as $mfaToken) {
                if (is_string($mfaToken)) {
                    $mfaToken = json_decode($mfaToken, true);
                }

                foreach ($this->type_filter_array as $type => $filter) {
                    if (false === $mfaToken['revoked'] && $mfaToken[$this->token_type_attr] === $type) {
                        $result[] = $filter;
                    }
                }
            }
        }
        $result = array_values(array_unique($result));
        $detect = new MobileDetect();
        $mobile_pref = $detect->isMobile();
        if ([] === $result) {
            return null;
        }
        $state['Attributes']['MFA_FILTERS'] = $result;
        if (null !== $this->preferred_filter && in_array($this->preferred_filter, $result, true)) {
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
        if (null === $this->reserved) {
            $this->reserved = '';
        }
        $state['Attributes']['Reserved'] = $this->reserved;
        $state['Attributes']['MFA_FILTER_INDEX'] = array_search($filter, $state['Attributes']['MFA_FILTERS'], true);
        Utils::runAuthProcFilter($filter, $this->configs[$filter], $state, $this->reserved);
    }
}
