<?php

$id = $json->id;
$nameCompany = $json->nameCompany;
$shopName = $json->shopName;
$subName = $json->subName;
$urlLogo = $json->urlLogo;
$domain = $json->domain;
$baseCurrency = $json->baseCurrency;
$emailSales = $json->emailSales;
$emailSupport = $json->emailSupport;
$cityDelivery = $json->cityDelivery;
$head = $json->head;
$poastHead = $json->poastHead;
$bookkeeper = $json->bookkeeper;
$mailAddress = $json->mailAddress;
$mainAddress = $json->mainAddress;
$phone = $json->phone;
$fax = $json->fax;
$nds = $json->nds;

$u = new seTable('main','m');
$u->select('m.*');
if ($id)
    $u->where('m.id=?', $id);
else $u->where("'m.lang='rus'");
$u->fetchOne();

if (!$u->is_manual_curr_rate)
    $u->addField('is_manual_curr_rate', 'TINYINT(1)', 0);

if (isset($nameCompany))
    $u->company = $nameCompany;
if (isset($shopName))
    $u->shopname = $shopName;
if (isset($subName))
    $u->subname = $subName;
if (isset($urlLogo))
    $u->logo = $urlLogo;
if (isset($domain))
    $u->domain = $domain;
if (isset($baseCurrency)) {
    $u->basecurr = $baseCurrency;
    $_SESSION['baseCurrency'] = $u->basecurr;
}
if (isset($emailSales))
    $u->esales = $emailSales;
if (isset($emailSupport))
    $u->esupport = $emailSupport;
if (isset($head))
    $u->director = $head;
if (isset($poastHead))
    $u->posthead = $poastHead;
if (isset($bookkeeper))
    $u->bookkeeper = $bookkeeper;
if (isset($mailAddress))
    $u->addr_f = $mailAddress;
if (isset($mainAddress))
    $u->addr_u = $mainAddress;
if (isset($phone))
    $u->phone = $phone;
if (isset($fax))
    $u->fax = $fax;
if (isset($nds))
    $u->nds = $nds;
if (isset($json->isManualCurrencyRate))
    $u->is_manual_curr_rate = $json->isManualCurrencyRate;
if (isset($json->isYAStore))
    $u->is_store = $json->isYAStore;
if (isset($json->isYAPickup))
    $u->is_pickup = $json->isYAPickup;
if (isset($json->isYADelivery))
    $u->is_delivery = $json->isYADelivery;
if (isset($json->costYALocalDelivery))
    $u->local_delivery_cost = $json->costYALocalDelivery;
if (isset($json->shopFolder))
    $u->folder = $json->shopFolder;

if ($id)
    $u->save();
else $id = $u->save();

$status = array();
$data['id'] = (int) $id;

if (!se_db_error()) {
    $status['status'] = 'ok';
    $status['data'] = $data;
} else {
    $status['status'] = 'error';
    $status['errortext'] = 'Не удаётся сохранить данные';
}

outputData($status);
