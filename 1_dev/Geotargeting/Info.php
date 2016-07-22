<?php

if (empty($json->ids))
    $json->ids[] = $_GET['id'];
$ids = implode(",", $json->ids);

$u = new seTable('shop_contacts', 'sc');
$u->select('sc.*, scg.idContact, scg.idCity');
$u->leftJoin('shop_contacts_geo scg', 'scg.id_contact = sc.id');
$u->groupBy("sc.id");
$u->where('sc.id IN (?)', $ids);
$result = $u->getList();

$items = array();
foreach($result as $item) {
    $contact = $item;
    $contact["isActive"] = (bool) $item["is_visible"];
    $contact["sortIndex"] = (int) $item["sort"];
    $items[] = $contact;
}

$data['count'] = sizeof($items);
$data['items'] = $items;

if (se_db_error()) {
    $status['status'] = 'error';
    $status['errortext'] = 'Не удаётся получить информацию о контакте для геотаргетинга!';
} else {
    $status['status'] = 'ok';
    $status['data'] = $data;
}

outputData($status);
