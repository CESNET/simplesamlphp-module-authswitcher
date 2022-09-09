<?php

declare(strict_types=1);

namespace SimpleSAML\Module\authswitcher;

use SimpleSAML\Error\Exception;
use SimpleSAML\Logger;
use SimpleSAML\Module;

/**
 * Methods not specific to this module.
 */
class Utils
{
    private const DEBUG_PREFIX = 'authswitcher:Utils: ';

    /**
     * Execute an auth proc filter.
     *
     * @see https://github.com/CESNET/perun-simplesamlphp-module/blob/master/lib/Auth/Process/ProxyFilter.php
     *
     * @param mixed $nestedClass
     * @param mixed $state
     * @param mixed $reserved
     */
    public static function runAuthProcFilter($nestedClass, array $config, &$state, $reserved)
    {
        list($module, $simpleClass) = explode(':', $nestedClass);
        $className = '\\SimpleSAML\\Module\\' . $module . '\\Auth\\Process\\' . $simpleClass;
        $authFilter = new $className($config, $reserved);
        $authFilter->process($state);
    }

    public static function areFilterModulesEnabled(array $filters)
    {
        $invalidModules = [];
        foreach ($filters as $filter) {
            list($module) = explode(':', $filter);
            if (!Module::isModuleEnabled($module)) {
                $invalidModules[] = $module;
            }
        }
        if ($invalidModules) {
            return $invalidModules;
        }

        return true;
    }

    public static function checkVariableInStateAttributes($state, $variable)
    {
        if (!isset($state['Attributes'][$variable])) {
            throw new Exception(self::DEBUG_PREFIX . $variable . ' missing in state attributes');
        }
    }

    public static function isMFAEnforced($state, $entityID = null)
    {
        if (!empty($state['Attributes'][AuthSwitcher::MFA_ENFORCE_SETTINGS])) {
            $settings = $state['Attributes'][AuthSwitcher::MFA_ENFORCE_SETTINGS];
            if (isset($settings[0])) {
                $settings = $settings[0];
            }
            if (is_string($settings)) {
                $settings = json_decode($settings, true, 3, JSON_THROW_ON_ERROR);
            }

            if (!empty($settings['all'])) {
                Logger::info(self::DEBUG_PREFIX . 'MFA was forced for all services by settings');
                return true;
            }

            $rpCategory = $state['Attributes'][AuthSwitcher::RP_CATEGORY][0] ?? 'other';

            $rpIdentifier = self::getEntityID($entityID, $state);

            if (!empty($settings['include_categories']) && in_array(
                $rpCategory,
                $settings['include_categories'],
                true
            ) && !in_array($rpIdentifier, $settings['exclude_rps'] ?? [], true)) {
                Logger::info(self::DEBUG_PREFIX . 'MFA was forced for this service by settings');
                return true;
            }

            Logger::info(self::DEBUG_PREFIX . 'MFA was not forced by settings');
            return false;
        }
        if (!empty($state['Attributes'][AuthSwitcher::MFA_ENFORCED])) {
            Logger::info(self::DEBUG_PREFIX . 'MFA was forced for all services by mfaEnforced');
            return true;
        }
        Logger::info(self::DEBUG_PREFIX . 'MFA was not forced');
        return false;
    }

    private static function getEntityID($entityID, $request)
    {
        if ($entityID === null) {
            return $request['SPMetadata']['entityid'];
        }
        if (is_callable($entityID)) {
            return call_user_func($entityID, $request);
        }
        if (!is_string($entityID)) {
            throw new Exception(
                self::DEBUG_PREFIX . 'Invalid configuration option entityID. It must be a string or a callable.'
            );
        }
        return $entityID;
    }
}
