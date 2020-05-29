<?php

namespace SimpleSAML\Module\authswitcher\Auth\Process;

use SimpleSAML\Auth\State;
use SimpleSAML\Configuration;
use SimpleSAML\Database;
use SimpleSAML\Error\Exception;
use SimpleSAML\Logger;
use SimpleSAML\Module\authswitcher\AuthSwitcher;
use SimpleSAML\Module\authswitcher\Utils;
use SimpleSAML\Module\saml\Error\NoAuthnContext;

class SwitchAuth extends \SimpleSAML\Auth\ProcessingFilter
{
    /* constants */
    private const DEBUG_PREFIX = 'authswitcher:SwitchAuth: ';

    private const UID_COL = 'userid';

    private const CONFIG_FILE = 'module_authswitcher.php';

    /** DB table for storing MFA methods */
    private $mfa_table = 'auth_method_setting';

    /* configurable attributes */

    /** Associative array with keys of the form 'module:filter', values are config arrays to be passed to filters. */
    private $configs = [];

    /** Second constructor parameter */
    private $reserved;

    /** State with exception handler set. */
    private $errorState;

    private $userCanSFA;

    private $userCanMFA;

    private $requestedSFA;

    private $requestedMFA;

    private $db;

    /** @override */
    public function __construct($config, $reserved)
    {
        parent::__construct($config, $reserved);

        $this->getConfig($config);

        $this->reserved = $reserved;
    }

    /** @override */
    public function process(&$state)
    {
        // pass requested => perform SFA and return pass
        // SFA requested => perform SFA and return SFA
        // MFA requested => perform MFA and return MFA

        $uid = $state['Attributes'][AuthSwitcher::UID_ATTR][0];
        $usersCapabilities = $this->getMFAForUid($uid);
        assert(!empty($usersCapabilities));
        $this->userCanSFA = self::SFAin($usersCapabilities);
        $this->userCanMFA = self::MFAin($usersCapabilities);
        $performMFA = !$this->userCanSFA;
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

            if (!empty($requestedContexts) && empty($supportedRequestedContexts)) {
                State::throwException(
                    $this->errorState,
                    new NoAuthnContext(
                        AuthSwitcher::SAML2_STATUS_REQUESTER
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

    /* logging */

    /** Log a warning. */
    private function warning($message)
    {
        Logger::warning(self::DEBUG_PREFIX . $message);
    }

    /** Get configuration parameters from the config array. */
    private function getConfig(array $config)
    {
        if (!is_array($config['configs'])) {
            throw new Exception(self::DEBUG_PREFIX . 'Configurations are missing.');
        }
        $filterModules = array_keys($config['configs']);
        $invalidModules = Utils::areFilterModulesEnabled($filterModules);
        if ($invalidModules !== true) {
            $this->warning('Some modules (' . implode(',', $invalidModules) . ')'
                . ' in the configuration are missing or disabled.');
        }
        $this->configs = $config['configs'];

        $this->db = Database::getInstance(
            Configuration::getOptionalConfig(self::CONFIG_FILE)->getConfigItem('store', [])
        );
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
     * If the Comparison attribute is set to “better”, “minimum”, or “maximum”,
     * the method of authentication must be stronger than, at least as strong as,
     * or no stronger than one of the specified authentication classes.
     * @throws NoAuthnContext
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
                if (!$this->userCanMFA && !$this->requestedSFA) {
                    $this->noAuthnContextResponder();
                }
                if (!$this->userCanSFA && !$this->requestedMFA) {
                    $this->noAuthnContextResponder();
                }
                break;
        }
    }

    /** @throws NoAuthnContext */
    private function noAuthnContextResponder()
    {
        State::throwException(
            $this->errorState,
            new NoAuthnContext(
                AuthSwitcher::SAML2_STATUS_RESPONDER
            )
        );
        exit;
    }

    private static function isMFAprefered($supportedRequestedContexts)
    {
        // assert($supportedRequestedContexts is a nonempty subset of AuthSwitcher::SUPPORTED)
        return $supportedRequestedContexts[0] === AuthSwitcher::MFA;
    }

    private function getMFAForUid($uid)
    {
        $active = $this->db->read('SELECT MAX(active) FROM ' . $this->mfa_table .
            ' WHERE ' . self::UID_COL . ' = :uid LIMIT 1', ['uid' => $uid])->fetchColumn();
        $result = [];
        if ($active === null || $active <= 0) {
            $result[] = AuthSwitcher::SFA;
        }
        if ($active !== null && $active >= 0) {
            $result[] = AuthSwitcher::MFA;
        }
        return $result;
    }

    private function getActiveMethod($uid)
    {
        return $this->db->read('SELECT method FROM ' . $this->mfa_table
            . ' WHERE ' . self::UID_COL . ' = :uid ORDER BY priority ASC', ['uid' => $uid])
            ->fetchColumn();
    }

    /**
     * Perform the appropriate MFA.
     */
    private function performMFA(&$state, $uid)
    {
        $method = $this->getActiveMethod($uid);

        if (empty($method)) {
            throw new Exception(self::DEBUG_PREFIX
                . 'Inconsistent data - no MFA methods for a user who should be able to do MFA.');
        }

        if (!isset($this->configs[$method])) {
            throw new Exception(self::DEBUG_PREFIX
                . 'Configuration for ' . $method . ' is missing.');
        }

        if (!isset($state[AuthSwitcher::MFA_BEING_PERFORMED])) {
            $state[AuthSwitcher::MFA_BEING_PERFORMED] = true;
        }
        Utils::runAuthProcFilter(
            $method,
            $this->configs[$method],
            $state,
            $this->reserved
        );
    }
}
