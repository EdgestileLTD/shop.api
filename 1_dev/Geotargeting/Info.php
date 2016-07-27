<?php

if (empty($json->ids))
    $json->ids[] = $_GET['id'];
$ids = implode(",", $json->ids);

function getCityById($id)
{
    $data = array('action' => 'city',
        'ids' => array($id));
    $data = http_build_query($data);
    $url = "https://api.siteedit.ru/api/geo/?".$data;
    $curl = curl_init($url);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    $data = json_decode(curl_exec($curl), true);
    if ($data["items"])
        return $data["items"][0]["name"];
    return null;
}

$u = new seTable('shop_contacts', 'sc');
$u->select('sc.*, scg.id_contact idContact, scg.id_city idCity');
$u->leftJoin('shop_contacts_geo scg', 'scg.id_contact = sc.id');
$u->groupBy("sc.id");
$u->where('sc.id IN (?)', $ids);
$result = $u->getList();

$items = array();
foreach($result as $item) {
    $contact = $item;
    $contact["isActive"] = (bool) $item["is_visible"];
    $contact["sortIndex"] = (int) $item["sort"];
    $contact["additionalPhones"] = $item["additional_phones"];
    $contact["city"] = getCityById($item["idCity"]);
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
