<?php

declare(strict_types=1);

/**
 * Mfa switch script.
 *
 * this script switch between mfa methods and perform defined method
 */

use SimpleSAML\Auth\ProcessingChain;
use SimpleSAML\Auth\State;
use SimpleSAML\Error\BadRequest;
use SimpleSAML\Module\authswitcher\Utils;
use SimpleSAML\Utils\HTTP;

if (!isset($_REQUEST['StateId'])) {
    throw new BadRequest('Missing required StateId or Module query parameter.');
}

$id = $_REQUEST['StateId'];

$sid = State::parseStateID($id);
if (null !== $sid['url']) {
    HTTP::checkURLAllowed($sid['url']);
}

$state = State::loadState($id, 'authSwitcher:request');
Utils::checkVariableInStateAttributes($state, 'MFA_RESULT');
Utils::checkVariableInStateAttributes($state, 'Config');
Utils::checkVariableInStateAttributes($state, 'Reserved');
Utils::checkVariableInStateAttributes($state, 'MFA_FILTERS');
Utils::checkVariableInStateAttributes($state, 'MFA_FILTER_INDEX');
$config = json_decode($state['Attributes']['Config'], true);
$mfaResult = $state['Attributes']['MFA_RESULT'];
$mfaFilterIndex = $state['Attributes']['MFA_FILTER_INDEX'];
if ('Authenticated' === $state['Attributes']['MFA_RESULT']) {
    ProcessingChain::resumeProcessing($state);
} else {
    if (count($state['Attributes']['MFA_FILTERS']) - 1 === $mfaFilterIndex) {
        $mfaFilterIndex = 0;
    } else {
        $mfaFilterIndex = $mfaFilterIndex + 1;
    }
    $state['Attributes']['MFA_FILTER_INDEX'] = $mfaFilterIndex;
    $method = $state['Attributes']['MFA_FILTERS'][$mfaFilterIndex];
    Utils::runAuthProcFilter($method, $config[$method], $state, $state['Attributes']['Reserved']);
}
