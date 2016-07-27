<?php

function getCitiesByIds($ids = array())
{
    $data = array('action' => 'city',
        'ids' => $ids);
    $data = http_build_query($data);
    $url = "https://api.siteedit.ru/api/geo/?" . $data;
    $curl = curl_init($url);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    $data = json_decode(curl_exec($curl), true);
    if ($data["items"]) {
        $result = array();
        foreach ($data["items"] as $item)
            $result[$item["id"]] = $item["name"];
        return $result;
    }
    return array();
}

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
$u->select('sc.*, scg.id_contact idContact, scg.id_city idCity');
$u->leftJoin('shop_contacts_geo scg', 'scg.id_contact = sc.id');
$u->groupBy("sc.id");
$u->orderBy('sort, sc.id');
$objects = $u->getList();

$items = array();
$idsCities = array();
foreach ($objects as $item) {
    $contact = $item;
    $contact["isActive"] = (bool)$item["is_visible"];
    $contact["sortIndex"] = (int)$item["sort"];
    $contact["additionalPhones"] = $item["additional_phones"];
    $contact["city"] = null;
    $idsCities[] = $item["idCity"];
    $items[] = $contact;
}

if ($idsCities) {
    $cities = getCitiesByIds($idsCities);
    foreach ($items as &$item)
        $item["city"] = $cities[$item["idCity"]];
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
