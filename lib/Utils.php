<?php

namespace SimpleSAML\Module\authswitcher;

use SimpleSAML\Error\Exception;

/**
 * Methods not specific to this module.
 */
class Utils
{
    /**
     * Execute an auth proc filter.
     *
     * @see https://github.com/CESNET/perun-simplesamlphp-module/blob/master/lib/Auth/Process/ProxyFilter.php
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
            if (! \SimpleSAML\Module::isModuleEnabled($module)) {
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
        if (! isset($state['Attributes'][$variable])) {
            throw new Exception('authswitcher:SwitchMfaMethods: ' . $variable . ' missing in state attributes');
        }
    }
}
