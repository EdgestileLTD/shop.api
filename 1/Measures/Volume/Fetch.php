<?php
/**
 * Created by PhpStorm.
 * User: developer
 * Date: 16.06.2017
 * Time: 20:42
 */


$u = new seTable('shop_measure_volume','smw');
$u->select("smw.designation");
$u->where('smw.is_base');
$baseMeasure = $u->fetchOne();
if (empty($baseMeasure))
    $baseMeasure = 'см3';
else $baseMeasure = $baseMeasure["designation"];

$u = new seTable('shop_settings', 'ss');
$u->select('ss.default, ssv.value');
$u->leftJoin('shop_setting_values ssv', 'ss.id = ssv.id_setting');
$u->where('ss.code = "volume_view"');
$viewVolume = $u->fetchOne();
if (!empty($viewVolume)) {
    if (!empty($viewVolume['value']))
        $viewVolume = $viewVolume['value'];
    else $viewVolume = $viewVolume['default'];
}

$u = new seTable('shop_settings', 'ss');
$u->select('ss.default, ssv.value');
$u->leftJoin('shop_setting_values ssv', 'ss.id = ssv.id_setting');
$u->where('ss.code = "volume_edit"');
$editVolume = $u->fetchOne();
if (!empty($editVolume)) {
    if (!empty($editVolume['value']))
        $editVolume = $editVolume['value'];
    else $editVolume = $editVolume['default'];
}

$u = new seTable('shop_measure_volume','smw');
$u->select('smw.*');
$result = $u->getList();

$items = array();
foreach($result as $item) {
    $item["value"] = number_format($item["value"], (int) $item["precision"]);
    $item["resultValue"] = "1 {$baseMeasure} = {$item["value"]} {$item["designation"]}";
    $item["isActive"] = (bool)$item["is_base"];
    if ($item["code"] == $viewVolume)
        $item["isView"] = true;
    if ($item["code"] == $editVolume)
        $item["isEdit"] = true;

    $items[] = $item;
}

$data['count'] = sizeof($items);
$data['items'] = $items;

$status = array();
if (!se_db_error()) {
    $status['status'] = 'ok';
    $status['data'] = $data;
} else {
    $status['status'] = 'error';
    $status['error'] = se_db_error();
}

outputData($status);
