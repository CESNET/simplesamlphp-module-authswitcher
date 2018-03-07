<?php
interface sspmod_authswitcher_DataAdapter {
    /** The constructor */
    function __construct();
    /** Return an array of sspmod_authswitcher_MethodParams */
    function getMethodsActiveForUidAndFactor(/*string*/ $uid, /*int*/ $factor);
    /** Test whether the user can login via SFA, MFA or both. */
    function getMFAForUid(/*string*/ $uid);
}
