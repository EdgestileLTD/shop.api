<?php

function convertFields($str)
{
    return str_replace('[idOption]', 'sov.id_option', $str);
}

$items = array();
$u = new seTable('shop_option_value', 'sov');
$u->select('sov.*, so.name `option`');
$u->leftJoin('shop_option so', 'sov.id_option = so.id');

if (!empty($json->filter))
    $filter = convertFields($json->filter);
if (!empty($filter))
    $where = $filter;
if (!empty($where))
    $u->where($where);
$u->orderby('sov.sort');

$objects = $u->getList();
foreach ($objects as $item) {
    $item['isActive'] = (bool)$item['is_active'];
    $item['sortIndex'] = (int)$item['sort'];
    $item['price'] = (real)$item['price'];
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