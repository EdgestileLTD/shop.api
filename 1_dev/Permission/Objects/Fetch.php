<?php

$u = new seTable('permission_object','po');
$u->select('po.*');
$u->groupby('id');

$count = $u->getListCount();
$objects = $u->getList();
foreach($objects as $item) {
    $object = null;
    $object['id'] = $item['id'];
    $object['code'] = $item['code'];
    $object['name'] = $item['name'];

    $items[] = $object;
}

$data['count'] = $count;
$data['items'] = $items;

$status = array();
if (!se_db_error()) {
    $status['status'] = 'ok';
    $status['data'] = $data;
} else {
    $status['status'] = 'error';
    $status['error'] = 'Не удаётся прочитать список объектов прав!';
}

outputData($status);