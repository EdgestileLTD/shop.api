<?php

$u = new seTable('shop_product_type', 'spt');
$u->select("spt.*, GROUP_CONCAT(sf.name SEPARATOR '; ') features");
$u->leftjoin('shop_product_type_feature sptf', 'sptf.id_type = spt.id');
$u->leftjoin('shop_feature sf', 'sptf.id_feature = sf.id');

if (!empty($json->searchText))
    $u->where("(name like '%{$json->searchText}%' OR id = '{$json->searchText}')");
$u->groupby('id');
if (!empty($json->sortBy))
    $u->orderby($json->sortBy, $json->sortOrder === 'desc');

$objects = $u->getList();
$items = array();
foreach ($objects as $item) {
    $type = array();
    $type["id"] = $item["id"];
    $type["name"] = $item["name"];
    $type["features"] = $item["features"];
    $items[] = $type;
}

$data['count'] = sizeof($objects);
$data['items'] = $items;

$status = array();
if (!se_db_error()) {
    $status['status'] = 'ok';
    $status['data'] = $data;
} else {
    $status['status'] = 'error';
    $status['error'] = 'Не удаётся прочитать список типов товаров';
}

outputData($status);