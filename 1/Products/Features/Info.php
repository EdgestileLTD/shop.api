<?php
$newTypes = array("string" => "S", "number" => "D", "bool" => "B", "list" => "L", "colorlist" => "CL");

if (empty($json->ids))
    $json->ids[] = $_GET['id'];
$ids = implode(",", $json->ids);

$u = new seTable('shop_feature', 'sf');
$u->select('sf.*, sfg.name AS namegroup');
$u->leftjoin('shop_feature_group sfg', 'sfg.id=sf.id_feature_group');
$u->where('sf.id in (?)', $ids);
$result = $u->getList();

$items = array();
foreach ($result as $item) {
    $feature = null;
    $feature['id'] = $item['id'];
    $feature['idGroup'] = $item['id_feature_group'];
    $feature['nameGroup'] = $item['namegroup'];
    $feature['name'] = $item['name'];
    $feature['type'] = $item['type'];
    $feature['valueType'] = $newTypes[$item['type']];
    $feature['description'] = $item['description'];
    $feature['imageFile'] = $item['image'];
    $feature['sortIndex'] = (int)$item['sort'];
    $feature['measure'] = $item['measure'];
    $feature['isYAMarket'] = (bool)$item['is_market'];
    $feature['placeholder'] = $item['placeholder'];
    if ($feature['imageFile']) {
        if (strpos($feature['imageFile'], '://') === false) {
            $feature['imageUrl'] = 'http://' . $json->hostname . "/images/rus/shopfeature/" . $feature['imageFile'];
            $feature['imageUrlPreview'] = "http://{$json->hostname}/lib/image.php?size=64&img=images/rus/shopfeature/" . $feature['imageFile'];
        } else {
            $feature['imageUrl'] = $feature['imageFile'];
            $feature['imageUrlPreview'] = $feature['imageFile'];
        }
    }
    $items[] = $feature;
}

$data['count'] = sizeof($items);
$data['items'] = $items;

if (se_db_error()) {
    $status['status'] = 'error';
    $status['error'] = 'Не удаётся получить информацию о параметре!';
} else {
    $status['status'] = 'ok';
    $status['data'] = $data;
}

outputData($status);