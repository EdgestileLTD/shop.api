<?php

if (empty($json->ids))
    $json->ids[] = $_GET['id'];
$ids = implode(",", $json->ids);

$u = new seTable('shop_providers', 'sp');
$u->select("sp.*, CONCAT_WS(' ', p.last_name, p.first_name, p.sec_name) user");
$u->leftjoin('person p', 'p.id = sp.id_user');
$u->where("sp.id in ($ids)");
$result = $u->getList();

$status = array();
$items = array();

foreach ($result as $item) {
    $provider = null;
    $provider["id"] = $item["id"];
    $provider["name"] = $item["name"];
    $provider["user"] = $item["user"];
    $provider["address"] = $item["address"];
    $provider["phone"] = $item["phone"];
    $provider["email"] = $item["email"];
    $provider["contact"] = $item["contact"];
    $items[] = $provider;
}

$data['count'] = sizeof($items);
$data['items'] = $items;

if (se_db_error()) {
    $status['status'] = 'error';
    $status['errortext'] = 'Не удаётся получить информацию о поставщике!';
} else {
    $status['status'] = 'ok';
    $status['data'] = $data;
}

outputData($status);