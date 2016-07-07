<?php

$u = new seTable('shop_status', 'ss');
$u->select('ss.id, ss.name, ss.code, ss.color, ss.is_visible isVisible, ss.note');

$objects = $u->getList();

$data['count'] = sizeof($objects);
$data['items'] = $objects;

$status = array();
if (!se_db_error()) {
    $status['status'] = 'ok';
    $status['data'] = $data;
} else {
    $status['status'] = 'error';
    $status['errortext'] = 'Не удаётся получить список статусов!';
}
outputData($status);