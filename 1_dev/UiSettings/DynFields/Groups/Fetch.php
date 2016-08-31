<?php

function convertFields($str)
{
    $str = str_replace('[data]', 'sug.data', $str);
    return $str;
}

if (!empty($json->filter))
    $filter = convertFields($json->filter);

$u = new seTable('shop_userfield_groups', 'sug');
$u->select('sug.*');
if (!empty($filter))
    $u->where($filter);
$u->orderBy('sort');

$result = $u->getList();

$items = array();
foreach ($result as $item) {
    $group = null;
    $group['id'] = $item['id'];
    $group['isActive'] = (bool)($item['enabled']);
    $group['name'] = $item['name'];
    $group['description'] = $item['description'];
    $group['sortIndex'] = (int)$item['sort'];
    $items[] = $group;
}

$data['count'] = sizeof($items);
$data['items'] = $items;

if (se_db_error()) {
    $status['status'] = 'error';
    $status['errortext'] = se_db_error();
} else {
    $status['status'] = 'ok';
    $status['data'] = $data;
}

outputData($status);
