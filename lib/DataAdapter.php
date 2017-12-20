<?php
interface sspmod_authswitcher_DataAdapter {
    /** The constructor gets the value of dataAdapterConfig */
    function __construct(array $config);
    /** Return an array of sspmod_authswitcher_MethodParams*/
    function getMethodsActiveForUidAndFactor(string $uid, int $factor);
}
