<?php
/**
 * Created by PhpStorm.
 * User: developer
 * Date: 10.06.2017
 * Time: 19:19
 */
if (empty($json->ids))
    $json->ids[] = $_GET['id'];
$ids = implode(",", $json->ids);

$u = new seTable('shop_option_value', 'sov');
$u->select('sov.*, so.name `option`');
$u->leftJoin('shop_option so', 'sov.id_option = sov.id');
$u->where('sov.id in (?)', $ids);
$result = $u->getList();

$items = array();
foreach ($result as $item) {
    $item['idOption'] = (int)$item['id_option'];
    $item['isActive'] = (bool)$item['is_active'];
    $item['sortIndex'] = (int)$item['sort'];
    $item['price'] = (real)$item['price'];
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
