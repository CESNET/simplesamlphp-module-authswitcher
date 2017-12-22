<?php
interface sspmod_authswitcher_DataAdapter {
    /** The constructor */
    function __construct();
    /** Return an array of sspmod_authswitcher_MethodParams */
    function getMethodsActiveForUidAndFactor(string $uid, int $factor);
}
