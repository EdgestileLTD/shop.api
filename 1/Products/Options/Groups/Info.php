<?php
/**
 * Created by PhpStorm.
 * User: developer
 * Date: 10.06.2017
 * Time: 12:12
 */
if (empty($json->ids))
    $json->ids[] = $_GET['id'];
$ids = implode(",", $json->ids);

$u = new seTable('shop_option_group','sog');
$u->where('sog.id in (?)', $ids);
$result = $u->getList();

$items = array();
foreach($result as $item) {
    $group = null;
    $group['id'] = $item['id'];
    $group['name'] = $item['name'];
    $group['isActive'] = (bool)$item['is_active'];
    $group['description'] = $item['description'];
    $group['sortIndex'] = (int) $item['sort'];
    $items[] = $group;
}

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
