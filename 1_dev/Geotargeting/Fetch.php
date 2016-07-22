<?php

$searchStr = $json->searchText;
$searchArr = explode(' ', $searchStr);

if (!empty($searchStr)) {
    if (strpos($searchStr, '?') === 0) {
        $search = substr($searchStr, 1);
        $search = convertFields($search);
    } else {
        foreach ($searchArr as $searchItem) {
            $searchItem = se_db_input($searchItem);
            if (!empty($search))
                $search .= " AND ";
            $search .= "(`sf`.`name` like '%$searchItem%')";
        }
    }
}

if (!empty($filter))
    $where = $filter;
if (!empty($search)) {
    if (!empty($where))
        $where = "(" . $where . ") AND (" . $search . ")";
    else $where = $search;
}

$u = new seTable('shop_contacts', 'sc');
$u->select('sc.*, scg.idContact, scg.idCity');
$u->leftJoin('shop_contacts_geo scg', 'scg.id_contact = sc.id');
$u->groupBy("sc.id");
$u->orderBy('sort, sc.id');
$objects = $u->getList();
$items = array();

foreach ($objects as $item) {
    $contact = $item;
    $contact["isActive"] = (bool) $item["is_visible"];
    $contact["sortIndex"] = (int) $item["sort"];
    $items[] = $contact;
}

$data['count'] = count($items);
$data['items'] = $items;

$status = array();
if (!se_db_error()) {
    $status['status'] = 'ok';
    $status['data'] = $data;
} else {
    $status['status'] = 'error';
    $status['errortext'] = 'Не удаётся получить список контактов геотаргетинга!';
}
outputData($status);
