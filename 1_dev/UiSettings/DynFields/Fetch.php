<?php

function convertFields($str)
{
    $str = str_replace('[idGroup]', 'suf.id_group', $str);
    $str = str_replace('[data]', 'suf.data', $str);
    return $str;
}

if (!empty($json->filter))
    $filter = convertFields($json->filter);

$types = array("string" => "S", "number" => "D", "text" => "T", "select" => "L", "checkbox" => "CB",
    "radio" => "R", "date" => "DT");

$typesDisplay = array("string" => "строка", "number" => "число", "text" => "текст", "select" => "список", "checkbox" => "флажок",
    "radio" => "переключатель", "date" => "дата");

$u = new seTable('shop_userfields', 'suf');
$u->select('suf.*, sug.name nameGroup');
$u->leftJoin('shop_userfield_groups sug', 'suf.id_group = sug.id');
if (!empty($filter))
    $u->where($filter);
$u->orderBy('sort');

$result = $u->getList();

$items = array();
foreach ($result as $item) {
    $field = null;
    $field['id'] = $item['id'];
    $field['idGroup'] = $item['id_group'];
    $field['code'] = $item['code'];
    $field['name'] = $item['name'];
    $field['nameGroup'] = $item['nameGroup'];
    $field['isActive'] = (bool)($item['enabled']);
    $field['isRequired'] = (bool)($item['required']);
    $field['valueType'] = $types[$item['type']];
    $field['displayType'] = $typesDisplay[$item['type']];
    $field['placeholder'] = $item['placeholder'];
    $field['dataTarget'] = $item['data'];
    $field['mask'] = $item['mask'];
    $field['description'] = $item['description'];
    $field['maxSize'] = (int)$item['max'];
    $field['minSize'] = (int)$item['min'];
    $field['sortIndex'] = (int)$item['sort'];
    $field['values'] = $item['values'];
    if ($item['values'])
        $field['listValues'] = explode(",", $item['values']);
    $items[] = $field;
}

$data['count'] = sizeof($items);
$data['items'] = $items;

if (se_db_error()) {
    $status['status'] = 'error';
    $status['errortext'] = "Не удаётся получить список полей!";
} else {
    $status['status'] = 'ok';
    $status['data'] = $data;
}

outputData($status);
