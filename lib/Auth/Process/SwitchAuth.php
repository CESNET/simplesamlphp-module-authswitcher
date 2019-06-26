<?php
namespace SimpleSAML\Module\authswitcher\Auth\Process;

class SwitchAuth extends \SimpleSAML\Auth\ProcessingFilter
{
    /* constants */
    const DEBUG_PREFIX = 'authswitcher:SwitchAuth: ';
    /** Whether to allow SFA for users that have MFA. */
    const ALLOW_SFA_WHEN_MFA_AVAILABLE = false;

    /* configurable attributes */
    /** Associative array with keys of the form 'module:filter', values are config arrays to be passed to filters. */
    private $configs = array();
    /** The factor (as in n-th factor authentication) of this filter instance. */
    private $factor = \SimpleSAML\Module\authswitcher\AuthSwitcherFactor::SECOND;

    /** Second constructor parameter */
    private $reserved;
    /** DataAdapter for getting users' settings. */
    private $dataAdapter = null;

    /** State with exception handler set. */
    private $errorState;

    private $userCanSFA;
    private $userCanMFA;
    private $requestedSFA;
    private $requestedMFA;

    /** Lazy getter for DataAdapter */
    private function getData()
    {
        if ($this->dataAdapter == null) {
            $this->dataAdapter = \SimpleSAML\Module\authswitcher\GetDataAdapter::getInstance();
        }
        return $this->dataAdapter;
    }
    
    /* logging */
    /** Log a warning. */
    private function warning($message)
    {
        \SimpleSAML\Logger::warning(self::DEBUG_PREFIX . $message);
    }
    /** Log an info. */
    private function info($message)
    {
        \SimpleSAML\Logger::info(self::DEBUG_PREFIX . $message);
    }

    /** @override */
    public function __construct($config, $reserved)
    {
        parent::__construct($config, $reserved);

        assert(interface_exists('\\SimpleSAML\\Module\\authswitcher\\DataAdapter'));

        $this->getConfig($config);

        $this->reserved = $reserved;
    }
    
    /** Get configuration parameters from the config array. */
    private function getConfig(array $config)
    {
        if (!is_array($config['configs'])) {
            throw new \SimpleSAML\Error\Exception(self::DEBUG_PREFIX . 'Configurations are missing.');
        }
        $filterModules = array_keys($config['configs']);
        $invalidModules = \SimpleSAML\Module\authswitcher\Utils::areFilterModulesEnabled($filterModules);
        if ($invalidModules !== true) {
            $this->warning('Some modules ('. implode(',', $invalidModules) .')'
                . ' in the configuration are missing or disabled.');
        }
        $this->configs = $config['configs'];
        
        if (isset($config['factor'])) {
            if (!is_int($config['factor'])
                || $config['factor'] < \SimpleSAML\Module\authswitcher\AuthSwitcher::FACTOR_MIN) {
                throw new \SimpleSAML\Error\Exception(self::DEBUG_PREFIX . 'Invalid configuration parameter factor.');
            }
            $this->factor = $config['factor'];
        }
    }

    /** Prepare before running auth proc filter (e.g. add atributes with secret keys) */
    private function prepareBeforeAuthProcFilter(\SimpleSAML\Module\authswitcher\MethodParams $method, &$state)
    {
        list($module, $simpleClass) = explode(":", $method->method);
        $filterMethodClassName = '\\SimpleSAML\\Module\\authswitcher\\Methods\\' . ucfirst($module) . $simpleClass;
        $filterMethod = new $filterMethodClassName($method);
        $filterMethod->process($state);
    }

    private static function SFAin($contexts)
    {
        return in_array(\SimpleSAML\Module\authswitcher\AuthSwitcher::SFA, $contexts)
            || in_array(\SimpleSAML\Module\authswitcher\AuthSwitcher::PASS, $contexts);
    }

    private static function MFAin($contexts)
    {
        return in_array(\SimpleSAML\Module\authswitcher\AuthSwitcher::MFA, $contexts);
    }

    /**
     * If the Comparison attribute is set to “better”, “minimum”, or “maximum”,
     * the method of authentication must be stronger than, at least as strong as,
     * or no stronger than one of the specified authentication classes.
     * @throws \SimpleSAML\Module\saml\Error\NoAuthnContext
     */
    private function testAuthnContextComparison($comparison)
    {
        switch ($comparison) {
            case 'better':
                if (!$this->userCanMFA || !$this->requestedSFA) {
                    $this->noAuthnContextResponder();
                }
                break;
            case 'minimum':
                if (!$this->userCanMFA && $this->requestedMFA) {
                    $this->noAuthnContextResponder();
                }
                break;
            case 'maximum':
                if (!$this->userCanSFA && $this->requestedSFA) {
                    $this->noAuthnContextResponder();
                }
                break;
            case 'exact':
            default:
                if (!$this->userCanSFA && !$this->requestedSFA) {
                    $this->noAuthnContextResponder();
                }
                if (!$this->userCanMFA && !$this->requestedMFA) {
                    $this->noAuthnContextResponder();
                }
                break;
        }
    }

    /** @throws \SimpleSAML\Module\saml\Error\NoAuthnContext */
    private function noAuthnContextResponder()
    {
        \SimpleSAML\Auth\State::throwException(
            $this->errorState,
            new \SimpleSAML\Module\saml\Error\NoAuthnContext(
                \SimpleSAML\Module\authswitcher\AuthSwitcher::SAML2_STATUS_RESPONDER
            )
        );
        exit;
    }

    private static function isMFAprefered($supportedRequestedContexts)
    {
        // assert($supportedRequestedContexts is a subset of \SimpleSAML\Module\authswitcher\AuthSwitcher::SUPPORTED)
        return count($supportedRequestedContexts) == 1
            || $supportedRequestedContexts[0] === \SimpleSAML\Module\authswitcher\AuthSwitcher::MFA;
    }

    /** @override */
    public function process(&$state)
    {
        // pass requested => perform SFA and return pass
        // SFA requested => perform SFA and return SFA
        // MFA requested => perform MFA and return MFA

        $uid = $state['Attributes'][\SimpleSAML\Module\authswitcher\AuthSwitcher::UID_ATTR][0];
        $usersCapabilities = $this->getData()->getMFAForUid($uid);
        assert(!empty($usersCapabilities));
        $this->userCanSFA = self::SFAin($usersCapabilities);
        $this->userCanMFA = self::MFAin($usersCapabilities);
        $performMFA = !$this->userCanSFA;
        // only SFA => SFA, inactive MFA (both) => SFA (if MFA not preferred by SP), active MFA => MFA
        // i.e. when possible, SFA is prefered

        $this->errorState = $state;
        unset($this->errorState[\SimpleSAML\Auth\State::EXCEPTION_HANDLER_URL]);
        $this->errorState[\SimpleSAML\Auth\State::EXCEPTION_HANDLER_FUNC]
            = ['\\SimpleSAML\\Module\\saml\\IdP\\SAML2', 'handleAuthError'];

        if (isset($state['saml:RequestedAuthnContext'])
            && isset($state['saml:RequestedAuthnContext']['AuthnContextClassRef'])) :
            $requestedAuthnContext = $state['saml:RequestedAuthnContext'];
            $requestedContexts = $requestedAuthnContext['AuthnContextClassRef'];
            $supportedRequestedContexts = array_intersect(
                $requestedAuthnContext['AuthnContextClassRef'],
                \SimpleSAML\Module\authswitcher\AuthSwitcher::SUPPORTED
            );

            if (!empty($requestedContexts) && empty($supportedRequestedContexts)) {
                \SimpleSAML\Auth\State::throwException(
                    $this->errorState,
                    new \SimpleSAML\Module\saml\Error\NoAuthnContext(
                        \SimpleSAML\Module\authswitcher\AuthSwitcher::SAML2_STATUS_REQUESTER
                    )
                );
                exit;
            }

            $this->requestedSFA = self::SFAin($supportedRequestedContexts);
            $this->requestedMFA = self::MFAin($supportedRequestedContexts);
            if (!empty($supportedRequestedContexts)) {
                // check for unsatisfiable combinations
                $this->testAuthnContextComparison($requestedAuthnContext['Comparison']);
                // switch to MFA if prefered
                if ($this->userCanMFA && self::isMFAprefered($supportedRequestedContexts)) {
                    $performMFA = true;
                }
            }
        endif;
        if ($performMFA) {
            $this->performMFA($state, $uid);
        }
    }

    /**
     * Perform the appropriate MFA.
     */
    private function performMFA(&$state, $uid)
    {
        $methods = $this->getData()->getMethodsActiveForUidAndFactor($uid, $this->factor);

        if (count($methods) == 0) {
            throw new \SimpleSAML\Error\Exception(self::DEBUG_PREFIX
                . 'Inconsistent DataAdapter - no MFA methods for a user who should be able to do MFA.');
        }

        $method = $this->chooseMethod($methods);
        $methodClass = $method->method;

        if (!isset($this->configs[$methodClass])) {
            throw new \SimpleSAML\Error\Exception(self::DEBUG_PREFIX
                . 'Configuration for ' . $methodClass . ' is missing.');
        }
        $this->prepareBeforeAuthProcFilter($method, $state);

        if (!isset($state[\SimpleSAML\Module\authswitcher\AuthSwitcher::MFA_BEING_PERFORMED])) {
            $state[\SimpleSAML\Module\authswitcher\AuthSwitcher::MFA_BEING_PERFORMED] = array();
        }
        $state[\SimpleSAML\Module\authswitcher\AuthSwitcher::MFA_BEING_PERFORMED][] = $method;
        \SimpleSAML\Module\authswitcher\Utils::runAuthProcFilter(
            $methodClass,
            $this->configs[$methodClass],
            $state,
            $this->reserved
        );
    }
    
    /**
     * Choose an appropriate method from the set.
     * @todo filter methods based on device (availability)
     */
    private function chooseMethod(array $methods)
    {
        return $methods[0];
    }
}
