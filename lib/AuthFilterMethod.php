<?php
namespace SimpleSAML\Module\authswitcher;

/** Concrete subclasses will be named \SimpleSAML\Module\authswitcher\Methods\modulenamefiltername */
abstract class AuthFilterMethod
{
    abstract public function process(&$state);
    abstract public function __construct(\SimpleSAML\Module\authswitcher\MethodParams $methodParams);
    abstract public function wasPerformed(&$state);
}
