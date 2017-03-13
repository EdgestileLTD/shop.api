<?php

$u = new seTable('shop_variables','sv');
$u->select('sv.*');
$u->orderby('name');

$objects = $u->getList();
foreach($objects as $item) {
    $var = null;
    $var['id'] = $item['id'];
    $var['name'] = $item['name'];
    $var['value'] = $item['value'];
    $items[] = $var;
}

$data['count'] = sizeof($items);
$data['items'] = $items;

$status = array();
if (!se_db_error()) {
    $status['status'] = 'ok';
    $status['data'] = $data;
} else {
    $status['status'] = 'error';
    $status['error'] = 'Не удаётся получить список динамических SEO переменных!';
}

outputData($status);

