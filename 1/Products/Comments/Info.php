<?php

if (empty($json->ids))
    $json->ids[] = $_GET['id'];
$ids = implode(",", $json->ids);

$u = new seTable('shop_comm', 'sc');
$u->select('sc.*, sp.id as idproduct, sp.name as nameproduct');
$u->innerjoin('shop_price sp', 'sp.id = sc.id_price');
$u->where("sc.id in ($ids)");

$objects = $u->getList();
foreach ($objects as $item) {
    $comm = null;
    $comm['id'] = $item['id'];
    $comm['date'] = date('d.m.Y', strtotime($item['date']));
    $comm['idProduct'] = $item['idproduct'];
    $comm['nameProduct'] = $item['nameproduct'];
    $comm['contactTitle'] = $item['name'];
    $comm['contactEmail'] = $item['email'];
    $comm['commentary'] = $item['commentary'];
    $comm['response'] = $item['response'];
    $comm['isShowing'] = $item['showing'] == 'Y';
    $comm['isActive'] = $item['is_active'] == "Y";
    $items[] = $comm;
}

$data['count'] = sizeof($items);
$data['items'] = $items;

$status = array();
if (!se_db_error()) {
    $status['status'] = 'ok';
    $status['data'] = $data;
} else {
    $status['status'] = 'error';
    $status['error'] = se_db_error();// 'Не удаётся прочитать информацию о комментарие!';
}
outputData($status);