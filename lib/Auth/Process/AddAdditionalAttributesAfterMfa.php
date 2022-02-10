<?php

declare(strict_types=1);

namespace SimpleSAML\Module\authswitcher\Auth\Process;

use SimpleSAML\Configuration;
use SimpleSAML\Module\authswitcher\ProxyHelper;
use SimpleSAML\Module\authswitcher\Utils;

class AddAdditionalAttributesAfterMfa extends \SimpleSAML\Auth\ProcessingFilter
{
    private const DEBUG_PREFIX = 'authswitcher:AddAdditionalAttributesAfterMfa: ';

    private $customAttrs;

    private $proxyMode = false;

    public function __construct($config, $reserved)
    {
        parent::__construct($config, $reserved);

        $config = Configuration::loadFromArray($config['config']);

        $this->customAttrs = $config->getArray('custom_attrs');
        $this->proxyMode = $config->getBoolean('proxy_mode', $this->proxyMode);
    }

    public function process(&$state)
    {
        if ($this->proxyMode) {
            $upstreamContext = ProxyHelper::fetchContextFromUpstreamIdp($state);
        } else {
            $upstreamContext = null;
        }

        if (Utils::wasMFAPerformed($state, $upstreamContext)) {
            foreach ($this->customAttrs as $key => $value) {
                $state['Attributes'][$key] = $value;
            }
        }
    }
}
