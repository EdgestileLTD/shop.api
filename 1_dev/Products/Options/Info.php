<?php
/**
 * Created by PhpStorm.
 * User: developer
 * Date: 10.06.2017
 * Time: 19:18
 */

if (empty($json->ids))
    $json->ids[] = $_GET['id'];
$ids = implode(",", $json->ids);

$u = new seTable('shop_option', 'so');
$u->where('so.id in (?)', $ids);
$result = $u->getList();

$items = array();
foreach ($result as $item) {
    $item['idGroup'] = $item['id_group'];
    $item['isActive'] = (bool)$item['is_active'];
    $item['isCounted'] = (bool)$item['is_counted'];
    $item['type'] = (int)$item['type'];
    $item['typePrice'] = (int)$item['typePrice'];
    $item['sortIndex'] = (int)$item['sort'];
    $item['imageFile'] = $item['image'];
    $items[] = $item;
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