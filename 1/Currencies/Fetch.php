<?php

IF (IS_EXT)
    $dirRoot = $_SERVER['DOCUMENT_ROOT'];
else $dirRoot = $_SERVER['DOCUMENT_ROOT'] . '/api';
$dirLib = $dirRoot . "/lib";
$scriptCurrency = $dirLib . "/lib_currency.php";
if ($isCurrLib = file_exists($scriptCurrency))
    require_once $scriptCurrency;

function formatCurrency($nameFront, $nameFlank, $value)
{
    if ($nameFront)
        return $nameFront . ' ' . $value;
    return $value . ' ' . $nameFlank;
}

$isRate = isset($json->rateDate) && !empty($json->rateDate);

$u = new seTable('main', 'm');
$u->select('m.is_manual_curr_rate, m.basecurr, mt.*');
$u->innerjoin('money_title mt', 'm.basecurr = mt.name');;
$base = $u->fetchOne();

$isManualMode = (bool)$base['is_manual_curr_rate'];
$baseNameFront = $base['name_front'];
$baseNameFlank = $base['name_flang'];
$baseCurrency = $base['basecurr'];

if (!$isRate)
    $json->rateDate = date('Y-m-d');
$u = new seTable('money');
if (!$u->isFindField('base_currency'))
    $u->addField('base_currency', "char(3) default 'RUB'", 1);
$u = new seTable('money_title', 'mt');
$u->select('mt.*');
$objects = $u->getList();
$rates = array();
if ($isManualMode) {
    $m = new seTable('money', 'm');
    $m->select('m.*');
    $m->where("m.base_currency = '$baseCurrency' AND m.date_replace <= '$json->rateDate'");
    $m->orderby('m. date_replace', 1);
    $rates = $m->getList();
}

$count = 0;
foreach ($objects as $item) {
    $count++;
    $curr = null;
    $curr['id'] = $item['id'];
    $curr['code'] = $item['name'];
    $curr['name'] = $item['title'];
    $curr['prefix'] = $item['name_front'];
    $curr['suffix'] = $item['name_flang'];
    $curr['cbrCode'] = $item['cbr_kod'];
    $curr['minSum'] = (float)$item['minsum'];
    if ($item['date_replace'])
        $curr['rateDate'] = date('Y-m-d', strtotime($item['date_replace']));
    else $curr['rateDate'] = null;

    if ($curr['code'] == $baseCurrency)
        $curr['rate'] = 1;
    else {
        $curr['rate'] = (float)$item['kurs'];
        if (!$isManualMode && $isCurrLib) {
            $baseValues = getCurrencyValues($baseCurrency);
            $currencyValues = getCurrencyValues($curr['code']);
            $baserate = (float)str_replace(",", ".", $baseValues['Value']) / str_replace(",", ".", $baseValues['Nominal']);
            if (empty($baserate)) $baserate = 1;
            $currrate = (float)str_replace(",", ".", $currencyValues['Value']) / str_replace(",", ".", $currencyValues['Nominal']);
            if (empty($currrate)) $currrate = 1;
            if ($baserate > 0)
                $curr['rate'] = ($currrate / $baserate);
            else $curr['rate'] = null;
            if ($curr['rate'] < 0)
                $curr['rate'] = null;
            $curr['rateDate'] = date('Y-m-d');
        } else {
            foreach ($rates as $rate) {
                if ($rate['name'] == $curr['code']) {
                    if (!empty($rate['kurs']))
                        $curr['rate'] = (float)$rate['kurs'];
                    break;
                }
            }
        }
    }

    $curr['rateDisplay'] = formatCurrency($curr['prefix'], $curr['suffix'], 1) . ' = ' . formatCurrency($baseNameFront,
            $baseNameFlank, $curr['rate']);
    $items[] = $curr;
}

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