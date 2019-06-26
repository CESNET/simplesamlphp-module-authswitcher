<?php
namespace SimpleSAML\Module\authswitcher;

/** Data adapter interface. */
interface DataAdapter
{
    /** The constructor */
    public function __construct();
    /** Return an array of \SimpleSAML\Module\authswitcher\MethodParams */
    public function getMethodsActiveForUidAndFactor($uid, /*int*/ $factor);
    /** Test whether the user can login via SFA, MFA or both. */
    public function getMFAForUid($uid);
}
