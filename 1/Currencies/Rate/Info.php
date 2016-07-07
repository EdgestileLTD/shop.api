<?php

IF (IS_EXT)
    $dirRoot = $_SERVER['DOCUMENT_ROOT'];
else $dirRoot = $_SERVER['DOCUMENT_ROOT'] . '/api';
$dirLib = $dirRoot . "/lib";
$scriptCurrency = $dirLib . "/lib_currency.php";
if ($isCurrLib = file_exists($scriptCurrency))
    require_once $scriptCurrency;

$curr = array();
if ($isCurrLib) {
    $baseCurrency = ($json->baseCurrency) ? $json->baseCurrency : 'RUB';
    $curr['id'] = $json->ids[0];
    $baseValues = getCurrencyValues($baseCurrency);
    $currencyValues = getCurrencyValues($json->code);
    $baserate = (float)str_replace(",", ".", $baseValues['Value']) / str_replace(",", ".", $baseValues['Nominal']);
    if (empty($baserate)) $baserate = 1;
    $currrate = (float)str_replace(",", ".", $currencyValues['Value']) / str_replace(",", ".", $currencyValues['Nominal']);
    if (empty($currrate)) $currrate = 1;
    $curr['rate'] = ($currrate / $baserate);
    $curr['rateDate'] = date('Y-m-d');
}
$items[] = $curr;

$data['count'] = $count;
$data['items'] = $items;

$status = array();
if (!se_db_error()) {
    $status['status'] = 'ok';
    $status['data'] = $data;
} else {
    $status['status'] = 'error';
    $status['errortext'] = se_db_error();
}
outputData($status);