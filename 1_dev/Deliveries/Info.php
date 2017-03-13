<?php

function getConditionsParams($id)
{
    $u = new seTable('shop_delivery_param', 'sdp');
    $u->select('sdp.*');
    $u->where('sdp.id_delivery = ?', $id);
    $result = array();
    $objects = $u->getList();
    foreach ($objects as $item) {
        $param = null;
        $param['id'] = $item['id'];
        $param['typeParam'] = $item['type_param'];
        $param['price'] = (real)$item['price'];
        $param['minValue'] = (real)$item['min_value'];
        $param['maxValue'] = (real)$item['max_value'];
        $param['priority'] = (int)$item['priority'];
        $param['operation'] = $item['operation'];
        $param['typePrice'] = $item['type_price'];
        $result[] = $param;
    }
    return $result;
}

function getConditionsRegions($id, $currency)
{
    $u = new seTable('shop_deliverytype', 'sd');
    $u->select('sd.*, sdr.id idGeo, sdr.id_city, sdr.id_country, sdr.id_region');
    $u->innerjoin('shop_delivery_region sdr', 'sd.id = sdr.id_delivery');
    $u->where('sd.id_parent = ?', $id);
    $u->orderBy('sd.note');

    $result = $u->getList();
    $regions = array();
    foreach ($result as $item) {
        $region = null;
        $region['id'] = $item['id'];
        $region['idGeo'] = $item['idGeo'];
        $region['idCountryTo'] = !empty($item['id_country']) ? $item['id_country'] : null;
        $region['idRegionTo'] = !empty($item['id_region']) ? $item['id_region'] : null;
        $region['idCityTo'] = !empty($item['id_city']) ? $item['id_city'] : null;
        $region['price'] = (float)$item['price'];
        $region['period'] = (int)$item['time'];
        $region['note'] = $item['note'];
        $region['currency'] = $currency;
        $region['isActive'] = (bool)($item['status'] == 'Y');
        $region['maxVolume'] = (int)$item['max_volume'];
        $region['maxWeight'] = (real)$item['max_weight'];
        $regions[] = $region;
    }
    return $regions;
}

function getIdsGroups($id)
{
    $u = new seTable('shop_deliverygroup', 'sd');
    $u->select('sd.id_group');
    $u->where('sd.id_type = ?', $id);
    $items = $u->getList();
    $result = array();
    foreach ($items as $item)
        $result[] = $item["id_group"];
    return $result;
}

function getGeoLocationsRegions($id)
{
    $u = new seTable('shop_delivery_region', 'sdr');
    $u->select('sdr.*');
    $u->where('sdr.id_delivery = ?', $id);

    $result = $u->getList();
    $regions = array();
    foreach ($result as $item) {
        $region = null;
        $region['id'] = $item['id'];
        $region['idCountryTo'] = !empty($item['id_country']) ? $item['id_country'] : null;
        $region['idRegionTo'] = !empty($item['id_region']) ? $item['id_region'] : null;
        $region['idCityTo'] = !empty($item['id_city']) ? $item['id_city'] : null;
        $regions[] = $region;
    }
    return $regions;
}

if (empty($json->ids))
    $json->ids[] = $_GET['id'];
$ids = implode(",", $json->ids);

$u = new seTable('shop_deliverytype', 'sd');
$u->select('sd.*, GROUP_CONCAT(DISTINCT(sdp.id_payment) SEPARATOR ";") AS idsPayments');
$u->leftjoin('shop_delivery_payment sdp', 'sd.id=sdp.id_delivery');
$u->where('sd.id in (?)', $ids);
$result = $u->getList();

$items = array();
foreach ($result as $item) {
    $delivery = null;
    $delivery['id'] = $item['id'];
    $delivery['code'] = $item['code'];
    $delivery['name'] = $item['name'];
    $delivery['period'] = (int)$item['time'];
    $delivery['price'] = (float)$item['price'];
    $delivery['idCityFrom'] = $item['city_from_delivery'];
    $delivery['isActive'] = (bool)($item['status'] == 'Y');
    $delivery['week'] = $item['week'];
    $delivery['note'] = $item['note'];
    $delivery['currency'] = $item['curr'];
    $delivery['onePosition'] = (bool)($item['forone'] == 'Y');
    $delivery['maxVolume'] = (int)$item['max_volume'];
    $delivery['maxWeight'] = (real)$item['max_weight'];
    $delivery['needAddress'] = (bool)($item['need_address'] == 'Y');
    $delivery['note'] = $item['note'];
    $idsPaySystems = explode(';', $item['idsPayments']);
    foreach ($idsPaySystems as $idGroup)
        $delivery['idsPaySystems'][] = $idGroup;
    $delivery['idsGroups'] = getIdsGroups($item['id']);
    $delivery['conditionsParams'] = getConditionsParams($item['id']);
    $delivery['conditionsRegions'] = getConditionsRegions($item['id'], $delivery['currency']);
    $delivery['geoLocationsRegions'] = getGeoLocationsRegions($item['id']);
    $items[] = $delivery;
}

$data['count'] = sizeof($items);
$data['items'] = $items;

if (se_db_error()) {
    $status['status'] = 'error';
    $status['error'] = se_db_error();
} else {
    $status['status'] = 'ok';
    $status['data'] = $data;
}

outputData($status);
