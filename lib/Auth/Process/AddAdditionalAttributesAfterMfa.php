<?php

declare(strict_types=1);

namespace SimpleSAML\Module\authswitcher\Auth\Process;

use SimpleSAML\Configuration;

class AddAdditionalAttributesAfterMfa extends \SimpleSAML\Auth\ProcessingFilter
{
    private const DEBUG_PREFIX = 'authswitcher:AddAdditionalAttributesAfterMfa: ';

    private $customAttrs;

    public function __construct($config, $reserved)
    {
        parent::__construct($config, $reserved);

        $config = Configuration::loadFromArray($config['config']);

        $this->customAttrs = $config->getArray('custom_attrs');
    }

    public function process(&$state)
    {
        if ($state[AuthSwitcher::MFA_PERFORMED]) {
            foreach ($this->customAttrs as $key => $value) {
                $state['Attributes'][$key] = $value;
            }
        }
    }
}
