<?php

$u = new seTable('main', 'm');
$u->select('m.*');
$mainList = $u->getList();

$status = array();
$data['count'] = sizeof($mainList);
$data['items'] = $mainList;

if (!se_db_error()) {
    $status['status'] = 'ok';
    $status['data'] = $data;
} else {
    $status['status'] = 'error';
    $status['error'] = 'Не удаётся получить список магазинов!';
}
outputData($status);