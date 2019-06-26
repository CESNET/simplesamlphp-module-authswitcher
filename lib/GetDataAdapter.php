<?php
namespace SimpleSAML\Module\authswitcher;

/** Class for getting the desired data adapter. */
class GetDataAdapter
{
  /** Get the desired class implementing the \SimpleSAML\Module\authswitcher\DataAdapter interface */
    public static function getInstance()
    {
        $dataAdapterClass = false;
        $config = \SimpleSAML\Configuration::getConfig('module_authswitcher.php');
        if ($config) {
            $config = $config->toArray();
            if (isset($config['dataAdapter'])) {
                $dataAdapterClass = $config['dataAdapter'];
            }
        }
        if (!$dataAdapterClass) {
            throw new Exception('Missing DataAdapter for authswitcher');
        }
        assert(in_array('\\SimpleSAML\\Module\\authswitcher\\DataAdapter', class_implements($dataAdapterClass)));
        return new $dataAdapterClass();
    }
}
