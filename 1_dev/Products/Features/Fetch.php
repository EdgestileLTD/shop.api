<?php

function convertFields($str)
{
    $str = str_replace('idGroup', 'sfg.id ', $str);
    return $str;
}

$isList = $json->isList;


$searchStr = $json->searchText;
$searchArr = explode(' ', $searchStr);

if (!empty($json->filter))
    $filter = convertFields($json->filter);
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

$u = new seTable('shop_feature', 'sf');
$u->select('sf.*, sfg.name AS nameGroup, GROUP_CONCAT(smg.name SEPARATOR ",") namesTypesGoods');
$u->leftjoin('shop_feature_group sfg', 'sfg.id=sf.id_feature_group');
$u->leftjoin('shop_group_feature sgf', 'sgf.id_feature=sf.id');
$u->leftjoin('shop_modifications_group smg', 'sgf.id_group=smg.id');
if (!empty($where))
    $u->where($where);
if ($isList) {
    if (!empty($where))
        $u->andWhere('sf.type="list" or sf.type="colorlist"');
    else $u->where('sf.type="list" or sf.type="colorlist"');
}
$u->groupby("sf.id");
$u->orderby('sort, sf.name');

$newTypes = array("string" => "S", "number" => "D", "bool" => "B", "list" => "L", "colorlist" => "CL");
$objects = $u->getList($json->offset, $json->limit);
$count = $u->getListCount();

foreach ($objects as $item) {
    $feature = null;
    $feature['id'] = $item['id'];
    $feature['name'] = $item['name'];
    $feature['idGroup'] = $item['id_feature_group'];
    $feature['nameGroup'] = $item['nameGroup'];
    $feature['namesTypesGoods'] = $item['namesTypesGoods'];
    $feature['description'] = $item['description'];
    $feature['imageFile'] = $item['image'];
    $feature['sortIndex'] = (int)$item['sort'];
    $feature['type'] = $item['type'];
    $feature['valueType'] = $newTypes[$item['type']];
    $feature['measure'] = $item['measure'];
    $feature['isSEO'] = (bool)$item['seo'];
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

$data['count'] = $count;
$data['items'] = $items;

$status = array();
if (!se_db_error()) {
    $status['status'] = 'ok';
    $status['data'] = $data;
} else {
    $status['status'] = 'error';
    $status['error'] = 'Не удаётся получить список парамеров!';
}
outputData($status);
