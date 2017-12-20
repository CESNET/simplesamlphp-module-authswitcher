<?php
interface sspmod_authswitcher_DataAdapter {
    function __construct($config);
    function getMethodsActiveForUidAndFactor($uid, $factor);
}
