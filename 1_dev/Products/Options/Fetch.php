<?php
/**
 * Created by PhpStorm.
 * User: developer
 * Date: 10.06.2017
 * Time: 19:18
 */

$items = array();
$u = new seTable('shop_option', 'so');
$u->select('so.*');
$u->orderby('sort');

$objects = $u->getList();
foreach ($objects as $item) {
    $item['isActive'] = (bool)$item['is_active'];
    $item['sortIndex'] = (int)$item['sort'];
    $items[] = $item;
}

$data['count'] = sizeof($items);
$data['items'] = $items;

$status = array();
if (!se_db_error()) {
    $status['status'] = 'ok';
    $status['data'] = $data;
} else {
    $status['status'] = 'error';
    $status['error'] = se_db_error();
}

outputData($status);
