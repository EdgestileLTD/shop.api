<?php

if (empty($json->ids))
    $json->ids[] = $_GET['id'];
$ids = implode(",", $json->ids);

function getFeatures($idType) {
    $result = array();

    $newTypes = array("string" => "S", "number" => "D", "bool" => "B", "list" => "L", "colorlist" => "CL");

    $u = new seTable('shop_product_type_feature', 'sptf');
    $u->select("sptf.id_feature, sf.name, sf.type, sf.sort, sf.measure");
    $u->innerjoin('shop_feature sf', 'sf.id = sptf.id_feature');
    $u->groupby("sf.id");
    $u->where('sptf.id_type = ?', $idType);
    $items = $u->getList();
    foreach ($items as $item) {
        $feature = array();
        $feature['id'] = $item['id_feature'];
        $feature['name'] = $item['name'];
        $feature['type'] = $item['type'];
        $feature['valueType'] = $newTypes[$item['type']];
        $feature['sortIndex'] = (int)$item['sort'];
        $feature['measure'] = $item['measure'];
        $result[] = $feature;
    }

    return $result;
}

$u = new seTable('shop_product_type', 'spt');
$u->select("spt.*");
$u->where("spt.id in ($ids)");
$result = $u->getList();

$status = array();
$items = array();
foreach($result as $item) {
    $type = array();
    $type['id'] = $item["id"];
    $type["name"] = $item["name"];
    $type['featuresList'] = getFeatures($type['id']);
    $items[] = $type;
}

$data['count'] = sizeof($items);
$data['items'] = $items;

if (se_db_error()) {
    $status['status'] = 'error';
    $status['errortext'] = 'Не удаётся получить информацию о типе товара!';
} else {
    $status['status'] = 'ok';
    $status['data'] = $data;
}
outputData($status);