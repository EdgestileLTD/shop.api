<?php

$idPayment = $json->idPayment;
$u = new seTable('bank_accounts', 'ba');
$u->select('ba.*');
if ($idPayment)
    $u->where('ba.id_payment=?', $idPayment);

$objects = $u->getList();
foreach ($objects as $item) {
    $value = null;
    $value['id'] = $item['id'];
    $value['idPayment'] = $item['id_payment'];
    $value['code'] = 'PAYMENT.' . strtoupper($item['codename']);
    $value['name'] = $item['title'];
    $value['value'] = $item['value'];
    $items[] = $value;
}

$data['count'] = sizeof($items);
$data['items'] = $items;

$status = array();
if (!mysql_error()) {
    $status['status'] = 'ok';
    $status['data'] = $data;
} else {
    $status['status'] = 'error';
    $status['error'] = mysql_error();
}
outputData($status);
