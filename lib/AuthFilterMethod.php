<?php
/** Concrete subclasses will be named aswAuthFilterMethod_modulename_filtername */
abstract class sspmod_authswitcher_AuthFilterMethod {
    abstract public function process(&$request);
    abstract public function __construct(sspmod_authswitcher_MethodParams $methodParams);
}
