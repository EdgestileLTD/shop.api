<?php

$v = new seTable('shop_mail', 'sm');
$v->select('sm.*');
$objects = $v->getList();

$letters = array();
foreach ($objects as $item) {
    $letter = null;
    $letter['id'] = $item['id'];
    $letter['name'] = $item['title'];
    $letter['code'] = $item['mailtype'];
    $letter['subject'] = $item['subject'];
    $letter['letter'] = $item['letter'];
    $letter['sortIndex'] = $item['itempost'];
    $letters[] = $letter;
}
$data['count'] = sizeof($objects);
$data['items'] = $letters;

$status = array();
if (!se_db_error()) {
    $status['status'] = 'ok';
    $status['data'] = $data;
} else {
    $status['status'] = 'error';
    $status['errortext'] = se_db_error();
}
outputData($status);