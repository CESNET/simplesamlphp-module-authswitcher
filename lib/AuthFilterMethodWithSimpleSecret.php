<?php
/** Abstract class for authentication methods which only require an array of secret string(s) in an attribute. */
abstract class sspmod_authswitcher_AuthFilterMethodWithSimpleSecret extends sspmod_authswitcher_AuthFilterMethod {
    /** Secret key etc. */
    protected $parameter;

    /** @override */
    public function __construct(sspmod_authswitcher_MethodParams $methodParams) {
        $this->parameter = explode(',', $methodParams->parameter);
    }
    
    /** @override */
    public function process(&$request) {
        $request['Attributes'][$this->getTargetFieldName()] = $this->parameter;
    }
    
    /** @return string */
    abstract public function getTargetFieldName();
}
