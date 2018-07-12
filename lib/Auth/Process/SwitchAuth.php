<?php
require_once __DIR__ . '/../../defaultAuthFilterMethods.php';

/**
 * @see https://github.com/simplesamlphp/simplesamlphp/blob/simplesamlphp-1.15/modules/saml/lib/Error/NoAuthnContext.php
 */
class NoAuthnContext extends sspmod_saml_Error
{
    /**
     * NoAuthnContext error constructor.
     *
     * @param string $responsible A string telling who is responsible for this error. Can be one of the following:
     *   - \SAML2\Constants::STATUS_RESPONDER: in case the error is caused by this SAML responder.
     *   - \SAML2\Constants::STATUS_REQUESTER: in case the error is caused by the SAML requester.
     * @param string|null $message A short message explaining why this error happened.
     * @param \Exception|null $cause An exception that caused this error.
     */
    public function __construct($responsible, $message = null, \Exception $cause = null)
    {
        parent::__construct($responsible, 'urn:oasis:names:tc:SAML:2.0:status:NoAuthnContext', $message, $cause);
    }
}

class sspmod_authswitcher_Auth_Process_SwitchAuth extends SimpleSAML_Auth_ProcessingFilter {
    /* constants */
    const DEBUG_PREFIX = 'authswitcher:SwitchAuth: ';
    /** Whether to allow SFA for users that have MFA. */
    const ALLOW_SFA_WHEN_MFA_AVAILABLE = false;

    /* configurable attributes */
    /** Associative array where keys are in form 'module:filter' and values are config arrays to be passed to those filters. */
    private $configs = array();
    /** The factor (as in n-th factor authentication) of this filter instance. */
    private $factor = sspmod_authswitcher_AuthSwitcherFactor::SECOND;

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
    private function getData() {
        if ($this->dataAdapter == null) {
            $this->dataAdapter = sspmod_authswitcher_GetDataAdapter::getInstance();
        }
        return $this->dataAdapter;
    }
    
    /* logging */
    /** Log a warning. */
    private function warning(/*string*/ $message) {
        SimpleSAML_Logger::warning(self::DEBUG_PREFIX . $message);
    }
    /** Log an info. */
    private function info(/*string*/ $message) {
        SimpleSAML_Logger::info(self::DEBUG_PREFIX . $message);
    }

    /** @override */
    public function __construct($config, $reserved) {
        parent::__construct($config, $reserved);

        assert(interface_exists('sspmod_authswitcher_DataAdapter'));

        $this->getConfig($config);

        $this->reserved = $reserved;
    }
    
    /** Get configuration parameters from the config array. */
    private function getConfig(array $config) {
        if (!is_array($config['configs'])) {
            throw new SimpleSAML_Error_Exception(self::DEBUG_PREFIX . 'Configurations are missing.');
        }
        $filterModules = array_keys($config['configs']);
        $invalidModules = sspmod_authswitcher_Utils::areFilterModulesEnabled($filterModules);
        if ($invalidModules !== true) {
            $this->warning('Some modules ('. implode(',', $invalidModules) .') in the configuration are missing or disabled.');
        }
        $this->configs = $config['configs'];
        
        if (isset($config['factor'])) {
            if (!is_int($config['factor']) || $config['factor'] < sspmod_authswitcher_AuthSwitcher::FACTOR_MIN) {
               throw new SimpleSAML_Error_Exception(self::DEBUG_PREFIX . 'Invalid configuration parameter factor.');
            }
            $this->factor = $config['factor'];
        }
    }

    /** Prepare before running auth proc filter (e.g. add atributes with secret keys) */
    private function prepareBeforeAuthProcFilter(sspmod_authswitcher_MethodParams $method, &$state) {
        list($module, $simpleClass) = explode(":", $method->method);
        $filterMethodClassName = "aswAuthFilterMethod_" . $module . "_" . $simpleClass;
        $filterMethod = new $filterMethodClassName($method);
        $filterMethod->process($state);
    }

    private static function SFAin($contexts) {
        return in_array(sspmod_authswitcher_AuthSwitcher::SFA, $contexts) || in_array(sspmod_authswitcher_AuthSwitcher::PASS, $contexts);
    }

    private static function MFAin($contexts) {
        return in_array(sspmod_authswitcher_AuthSwitcher::MFA, $contexts);
    }

    /**
     * If the Comparison attribute is set to “better”, “minimum”, or “maximum”,
     * the method of authentication must be stronger than, at least as strong as, or no stronger than one of the specified authentication classes.
     * @throws NoAuthnContext
     */
    private function testAuthnContextComparison($comparison) {
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

    /** @throws NoAuthnContext */
    private function noAuthnContextResponder() {
        SimpleSAML_Auth_State::throwException($this->errorState, new NoAuthnContext(sspmod_authswitcher_AuthSwitcher::SAML2_STATUS_RESPONDER));
        exit;
    }

    private static function isMFAprefered($supportedRequestedContexts) {
        // assert($supportedRequestedContexts is a subset of sspmod_authswitcher_AuthSwitcher::SUPPORTED)
        return count($supportedRequestedContexts) == 1 || $supportedRequestedContexts[0] === sspmod_authswitcher_AuthSwitcher::MFA;
    }

    /** @override */
    public function process(&$state) {
        // pass requested => perform SFA and return pass
        // SFA requested => perform SFA and return SFA
        // MFA requested => perform MFA and return MFA

        $uid = $state['Attributes'][sspmod_authswitcher_AuthSwitcher::UID_ATTR][0];
        $usersCapabilities = $this->getData()->getMFAForUid($uid);
        assert(!empty($usersCapabilities));
        $this->userCanSFA = self::SFAin($usersCapabilities);
        $this->userCanMFA = self::MFAin($usersCapabilities);
        $performMFA = !$this->userCanSFA; // only SFA => SFA, inactive MFA (both) => SFA (if MFA not preferred by SP), active MFA => MFA
        // i.e. when possible, SFA is prefered

        $this->errorState = $state;
        unset($this->errorState[SimpleSAML_Auth_State::EXCEPTION_HANDLER_URL]);
        $this->errorState[SimpleSAML_Auth_State::EXCEPTION_HANDLER_FUNC] = array('sspmod_saml_IdP_SAML2', 'handleAuthError');

        if (isset($state['saml:RequestedAuthnContext']) && isset($state['saml:RequestedAuthnContext']['AuthnContextClassRef'])):
        $requestedAuthnContext = $state['saml:RequestedAuthnContext'];
        $requestedContexts = $requestedAuthnContext['AuthnContextClassRef'];
        $supportedRequestedContexts = array_intersect($requestedAuthnContext['AuthnContextClassRef'], sspmod_authswitcher_AuthSwitcher::SUPPORTED);

        if (!empty($requestedContexts) && empty($supportedRequestedContexts)) {
            SimpleSAML_Auth_State::throwException($this->errorState, new NoAuthnContext(sspmod_authswitcher_AuthSwitcher::SAML2_STATUS_REQUESTER));
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
        if ($performMFA) $this->performMFA($state, $uid);
    }

    /**
     * Perform the appropriate MFA.
     */
    private function performMFA(&$state, $uid) {
        $methods = $this->getData()->getMethodsActiveForUidAndFactor($uid, $this->factor);

        if (count($methods) == 0) {
            throw new SimpleSAML_Error_Exception(self::DEBUG_PREFIX . 'Inconsistent DataAdapter - no MFA methods for a user who should be able to do MFA.');
        }

        $method = $this->chooseMethod($methods);
        $methodClass = $method->method;

        if (!isset($this->configs[$methodClass])) {
            throw new SimpleSAML_Error_Exception(self::DEBUG_PREFIX . 'Configuration for ' . $methodClass . ' is missing.');
        }
        $this->prepareBeforeAuthProcFilter($method, $state);

        if (!isset($state[sspmod_authswitcher_AuthSwitcher::MFA_BEING_PERFORMED])) {
            $state[sspmod_authswitcher_AuthSwitcher::MFA_BEING_PERFORMED] = array();
        }
        $state[sspmod_authswitcher_AuthSwitcher::MFA_BEING_PERFORMED][] = $method;
        sspmod_authswitcher_Utils::runAuthProcFilter($methodClass, $this->configs[$methodClass], $state, $this->reserved);
    }
    
    /**
     * Choose an appropriate method from the set.
     * @todo filter methods based on device (availability)
     */
    private function chooseMethod(array $methods) {
        return $methods[0];
    }
}

