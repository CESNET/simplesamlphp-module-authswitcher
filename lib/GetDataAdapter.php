<?php
class sspmod_authswitcher_GetDataAdapter {
  /** Get the desired class implementing the sspmod_authswitcher_DataAdapter interface */
  public static function getInstance() {
    $dataAdapterClass = false;
    $config = SimpleSAML_Configuration::getConfig('module_authswitcher.php');
    if ($config) {
      $config = $config->toArray();
      if (isset($config['dataAdapter'])) $dataAdapterClass = $config['dataAdapter'];
    }
    if (!$dataAdapterClass) throw new Exception('Missing DataAdapter for authswitcher');
    assert(in_array('sspmod_authswitcher_DataAdapter', class_implements($dataAdapterClass)));
    return new $dataAdapterClass();
  }
}
