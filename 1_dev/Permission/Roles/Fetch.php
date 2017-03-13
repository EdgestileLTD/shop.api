<?php

$u = new seTable('permission_role','pr');
$u->select('pr.*');
$u->groupby('id');

$count = $u->getListCount();
$objects = $u->getList();
foreach($objects as $item) {
    $role = null;
    $role['id'] = $item['id'];
    $role['name'] = $item['name'];
    $role['description'] = $item['description'];

    $items[] = $role;
}

$data['count'] = $count;
$data['items'] = $items;

$status = array();
if (!se_db_error()) {
    $status['status'] = 'ok';
    $status['data'] = $data;
} else {
    $status['status'] = 'error';
    $status['error'] = 'Не удаётся прочитать список ролей!';
}

outputData($status);