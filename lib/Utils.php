<?php
/** Methods not specific to this module. */
class sspmod_authswitcher_Utils {
    /** Execute an auth proc filter.
     * @see https://github.com/CESNET/perun-simplesamlphp-module/blob/master/lib/Auth/Process/ProxyFilter.php */
    public static function runAuthProcFilter($nestedClass, $config, &$request, $reserved) {
        list($module, $simpleClass) = explode(":", $nestedClass);
        $className = 'sspmod_'.$module.'_Auth_Process_'.$simpleClass;
        $authFilter = new $className($config, $reserved);
        $authFilter->process($request);
    }
    
    /** Check if all modules for the specified filters are installed and enabled. */
    public static function areFilterModulesEnabled($filters) {
        foreach ($filters as $filter) {
            list($module) = explode(":", $filter);
            if (!SimpleSAML_Module::isModuleEnabled($module)) return false;
        }
        return true;
    }
}
