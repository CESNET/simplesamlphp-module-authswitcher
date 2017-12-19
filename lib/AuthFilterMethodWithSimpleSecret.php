<?php
/** Abstract class for authentication methods which only require an array of secret string(s) in an attribute. */
abstract class sspmod_authswitcher_AuthFilterMethodWithSimpleSecret extends sspmod_authswitcher_AuthFilterMethod {
    protected $parameter;

    public function __construct($methodParams) {
        $this->parameter = explode(',', $methodParams->parameter);
    }
    
    /** @override */
    public function process(&$request) {
        $request['Attributes'][$this->getTargetFieldName()] = $this->parameter;
    }
    
    abstract public function getTargetFieldName();
}
