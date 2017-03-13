<?php

$url_img = 'http://' . $json->hostname . '/images/';
$u = new seTable('shop_payment', 'sp');
$u->select('sp.id, sp.logoimg, `name_payment`, `active`, `is_test`, `sort`, lang, ident, way_payment');
$u->orderby('sort');
$u->addorderby('id');
$objects = $u->getList();
$paySystems = array();
foreach ($objects as $item) {
    $paySystem = null;
    $paySystem['id'] = $item['id'];
    $paySystem['imageFile'] = $item['logoimg'];
    $paySystem['identifier'] = $item['ident'];
    $paySystem['name'] = $item['name_payment'];
    $paySystem['isActive'] = $item['active'] == 'Y';
    $paySystem['isTestMode'] = $item['is_test'] == 'Y';
    $paySystem['sortIndex'] = (int)$item['sort'];
    $paySystem['wayPayment'] = $item['way_payment'];
    if ($paySystem['imageFile']) {
        if (strpos($paySystem['imageFile'], "://") === false) {
            $paySystem['imageUrl'] = 'http://' . $json->hostname . "/images/rus/shoppayment/" . $paySystem['imageFile'];
            $paySystem['imageUrlPreview'] = "http://{$json->hostname}/lib/image.php?size=64&img=images/rus/shoppayment/" . $paySystem['imageFile'];
        } else {
            $paySystem['imageUrl'] = $paySystem['imageFile'];
            $paySystem['imageUrlPreview'] = $paySystem['imageFile'];
        }
    }
    $paySystems[] = $paySystem;
}

$data['count'] = sizeof($objects);
$data['items'] = $paySystems;

$status = array();
if (!se_db_error()) {
    $status['status'] = 'ok';
    $status['data'] = $data;
} else {
    $status['status'] = 'error';
    $status['error'] = 'Не удаётся получить список платежных систем!';
}

outputData($status);