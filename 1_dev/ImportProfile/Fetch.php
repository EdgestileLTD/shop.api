<?php

$u = new seTable('import_profile', 'ip');
$u->select('ip.*');

$items = $u->getList();

$data['count'] = sizeof($items);
$data['items'] = $items;

$status = array();
if (!se_db_error()) {
    $status['status'] = 'ok';
    $status['data'] = $data;
} else {
    $status['status'] = 'error';
    $status['error'] = 'Не удаётся получить список профилей!';
}

outputData($status);