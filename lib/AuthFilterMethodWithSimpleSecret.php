<?php
namespace SimpleSAML\Module\authswitcher;

/** Abstract class for authentication methods which only require an array of secret string(s) in an attribute. */
abstract class AuthFilterMethodWithSimpleSecret extends \SimpleSAML\Module\authswitcher\AuthFilterMethod
{
    /** Secret key etc. */
    protected $parameter;

    /** @override */
    public function __construct(\SimpleSAML\Module\authswitcher\MethodParams $methodParams)
    {
        $this->parameter = explode(',', $methodParams->parameter);
    }
    
    /** @override */
    public function process(&$state)
    {
        $state['Attributes'][$this->getTargetFieldName()] = $this->parameter;
    }
    
    /** @return string */
    abstract public function getTargetFieldName();
}
