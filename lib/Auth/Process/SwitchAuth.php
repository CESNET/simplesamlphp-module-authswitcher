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
    /** DataAdapter implementation class name */
    private $dataAdapterClassName;

    /** Second constructor parameter */
    private $reserved;
    /** DataAdapter for getting users' settings. */
    private $dataAdapter = null;

    /** Lazy getter for DataAdapter */
    private function getData() {
        if ($this->dataAdapter == null) {
            $className = $this->dataAdapterClassName;
            $this->dataAdapter = new $className();
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
        
        if (!is_string($config['dataAdapterClassName'])) {
            throw new SimpleSAML_Error_Exception(self::DEBUG_PREFIX . 'dataAdapterClassName is missing.');
        }
        if (!class_exists($config['dataAdapterClassName'])) {
            throw new SimpleSAML_Error_Exception(self::DEBUG_PREFIX . 'dataAdapterClassName does not exist.');
        }
        if (!in_array('sspmod_authswitcher_DataAdapter', class_implements($config['dataAdapterClassName']))) {
            throw new SimpleSAML_Error_Exception(self::DEBUG_PREFIX . 'dataAdapterClassName does not implement sspmod_authswitcher_DataAdapter');
        }
        $this->dataAdapterClassName = $config['dataAdapterClassName'];
    }

    /** Prepare before running auth proc filter (e.g. add atributes with secret keys) */
    private function prepareBeforeAuthProcFilter(sspmod_authswitcher_MethodParams $method, &$state) {
        list($module, $simpleClass) = explode(":", $method->method);
        $filterMethodClassName = "aswAuthFilterMethod_" . $module . "_" . $simpleClass;
        $filterMethod = new $filterMethodClassName($method);
        $filterMethod->process($state);
    }
    
    /** @override */
    public function process(&$state) {
        $uid = $state['Attributes'][sspmod_authswitcher_AuthSwitcher::UID_ATTR][0];
        $method = $this->prepareMFA($state, $uid);
        $userCanSFA = self::ALLOW_SFA_WHEN_MFA_AVAILABLE || $method === false;
        $userCanMFA = $method !== false;

        $sfa = sspmod_authswitcher_AuthSwitcher::SFA;
        $mfa = sspmod_authswitcher_AuthSwitcher::MFA;
        $both = array($sfa, $mfa);

        $errorState = $state;
        unset($errorState[SimpleSAML_Auth_State::EXCEPTION_HANDLER_URL]);
        $errorState[SimpleSAML_Auth_State::EXCEPTION_HANDLER_FUNC] = array('sspmod_saml_IdP_SAML2', 'handleAuthError');

        if (isset($state['saml:RequestedAuthnContext']) && isset($state['saml:RequestedAuthnContext']['AuthnContextClassRef'])):
        $requestedAuthnContext = $state['saml:RequestedAuthnContext'];
        $requestedContexts = $requestedAuthnContext['AuthnContextClassRef'];
        $supportedRequestedContexts = array_intersect($requestedAuthnContext['AuthnContextClassRef'], $both);
        if (!empty($requestedContexts) && empty($supportedRequestedContexts)) { // something other than SFA or MFA was requested
            SimpleSAML_Auth_State::throwException($errorState, new NoAuthnContext('urn:oasis:names:tc:SAML:2.0:status:Requester'));
            exit;
        }

        if (!empty($supportedRequestedContexts)) {
            // If the Comparison attribute is set to “better”, “minimum”, or “maximum”,
            // the method of authentication must be stronger than, at least as strong as, or no stronger than one of the specified authentication classes.
            switch ($requestedAuthnContext['Comparison']) {
                case 'better':
                    if (!$userCanMFA || !in_array($sfa, $supportedRequestedContexts)) {
                        SimpleSAML_Auth_State::throwException($errorState, new NoAuthnContext('urn:oasis:names:tc:SAML:2.0:status:Responder'));
                        exit;
                    }
                break;
                case 'minimum':
                    if (!$userCanMFA && in_array($mfa, $supportedRequestedContexts)) {
                        SimpleSAML_Auth_State::throwException($errorState, new NoAuthnContext('urn:oasis:names:tc:SAML:2.0:status:Responder'));
                        exit;
                    }
                break;
                case 'maximum':
                    if (!$userCanSFA && in_array($sfa, $supportedRequestedContexts)) {
                        SimpleSAML_Auth_State::throwException($errorState, new NoAuthnContext('urn:oasis:names:tc:SAML:2.0:status:Responder'));
                        exit;
                    }
                break;
                case 'exact':
                default:
                    if (!$userCanSFA && empty(array_diff($supportedRequestedContexts, array($sfa)))) {
                        SimpleSAML_Auth_State::throwException($errorState, new NoAuthnContext('urn:oasis:names:tc:SAML:2.0:status:Responder'));
                        exit;
                    }
                    if (!$userCanMFA && empty(array_diff($supportedRequestedContexts, array($mfa)))) {
                        SimpleSAML_Auth_State::throwException($errorState, new NoAuthnContext('urn:oasis:names:tc:SAML:2.0:status:Responder'));
                        exit;
                    }
                break;
            }
            // SFA is prefered
            if ($userCanSFA && $userCanMFA && empty(array_diff($both, $supportedRequestedContexts)) && array_search($sfa, $supportedRequestedContexts) < array_search($mfa, $supportedRequestedContexts)) {
                $method = false;
            }
        }
        endif;

        if ($method) $this->performMFA($state, $method);
    }

    private function prepareMFA(&$state, $uid) {
        $methods = $this->getData()->getMethodsActiveForUidAndFactor($uid, $this->factor);

        if (count($methods) == 0) {
            $this->logNoMethodsForFactor($uid, $this->factor);
            return false;
        }

        return $this->chooseMethod($methods);
    }

    private function performMFA(&$state, $method) {
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
    
    /** Choose an appropriate method from the set.
     * @todo filter methods based on device (availability)
     */
    private function chooseMethod(array $methods) {
        return $methods[0];
    }
    
    /** Log that a user has no methods for n-th factor. */
    private function logNoMethodsForFactor(/*string*/ $uid, /*int*/ $factor) {
        if ($factor == sspmod_authswitcher_AuthSwitcher::FACTOR_MIN) {
            $this->info('User '.$uid.' has no methods for factor '.$factor.'. MFA not performed at all.');
        } else {
            $this->info('User '.$uid.' has no methods for factor '.$factor);
        }
    }
}

