<?php
/**
 * Created by PhpStorm.
 * User: developer
 * Date: 17.06.2017
 * Time: 12:30
 */
if (empty($json->ids))
    $json->ids[] = $_GET['id'];
$ids = implode(",", $json->ids);

$u = new seTable('shop_measure_volume','smv');
$u->where('smv.id in (?)', $ids);
$items = $u->getList();

$data['count'] = sizeof($items);
$data['items'] = $items;

if (se_db_error()) {
    $status['status'] = 'error';
    $status['error'] = se_db_error();
} else {
    $status['status'] = 'ok';
    $status['data'] = $data;
}

outputData($status);