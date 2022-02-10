<?php

declare(strict_types=1);

namespace SimpleSAML\Module\authswitcher;

use SimpleSAML\Error\Exception;
use SimpleSAML\Module;

/**
 * Methods not specific to this module.
 */
class Utils
{
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

    /**
     * Check whether MFA was performed, either locally (by running MFA auth proc filters) or at the upstream IdP.
     *
     * @param mixed      $state
     * @param mixed|null $upstreamContext
     */
    public static function wasMFAPerformed($state, $upstreamContext = null)
    {
        return !empty($state[AuthSwitcher::MFA_BEING_PERFORMED]) || AuthSwitcher::MFA === $upstreamContext;
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
            throw new Exception('authswitcher:SwitchMfaMethods: ' . $variable . ' missing in state attributes');
        }
    }
}
