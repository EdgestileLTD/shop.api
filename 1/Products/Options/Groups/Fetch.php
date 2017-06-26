<?php
/**
 * Created by PhpStorm.
 * User: developer
 * Date: 09.06.2017
 * Time: 11:14
 */

$items = array();
$u = new seTable('shop_option_group', 'sog');
$u->select('sog.*');
$u->orderby('sort');

$objects = $u->getList();
foreach ($objects as $item) {
    $group = null;
    $group['id'] = $item['id'];
    $group['name'] = $item['name'];
    $group['description'] = $item['description'];
    $group['isActive'] = (bool)$item['is_active'];
    $group['sortIndex'] = (int)$item['sort'];
    $items[] = $group;
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
