<?php

$u = new seTable('shop_providers', 'sp');
$u->select("sp.*, CONCAT_WS(' ', p.last_name, p.first_name, p.sec_name) user");
$u->leftjoin('person p', 'p.id = sp.id_user');

if (!empty($json->searchText))
    $u->where("(name like '%{$json->searchText}%' OR p.last_name = '{$json->searchText}')");
$u->groupby('sp.id');
if (!empty($json->sortBy))
    $u->orderby($json->sortBy, $json->sortOrder === 'desc');

$objects = $u->getList();
$items = array();
foreach ($objects as $item) {
    $provider = array();
    $provider["id"] = $item["id"];
    $provider["name"] = $item["name"];
    $provider["user"] = $item["user"];
    $provider["address"] = $item["address"];
    $provider["phone"] = $item["phone"];
    $provider["email"] = $item["email"];
    $provider["contact"] = $item["contact"];
    $items[] = $provider;
}

$data['count'] = sizeof($objects);
$data['items'] = $items;

$status = array();
if (!se_db_error()) {
    $status['status'] = 'ok';
    $status['data'] = $data;
} else {
    $status['status'] = 'error';
    $status['errortext'] = 'Не удаётся прочитать список поставщиков';
}

outputData($status);