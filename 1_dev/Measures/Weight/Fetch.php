<?php
/**
 * Created by PhpStorm.
 * User: developer
 * Date: 15.06.2017
 * Time: 17:46
 */

$u = new seTable('shop_measure_weight','smw');
$u->select("smw.designation");
$u->where('smw.is_base');
$baseMeasure = $u->fetchOne();
if (empty($baseMeasure))
    $baseMeasure = 'г';
else $baseMeasure = $baseMeasure["designation"];

$u = new seTable('shop_settings', 'ss');
$u->select('ss.default, ssv.value');
$u->leftJoin('shop_setting_values ssv', 'ss.id = ssv.id_setting');
$u->where('ss.code = "weight_view"');
$viewMeasure = $u->fetchOne();
if (!empty($viewMeasure)) {
    if (!empty($viewMeasure['value']))
        $viewMeasure = $viewMeasure['value'];
    else $viewMeasure = $viewMeasure['default'];
}

$u = new seTable('shop_settings', 'ss');
$u->select('ss.default, ssv.value');
$u->leftJoin('shop_setting_values ssv', 'ss.id = ssv.id_setting');
$u->where('ss.code = "weight_edit"');
$editMeasure = $u->fetchOne();
if (!empty($editMeasure)) {
    if (!empty($editMeasure['value']))
        $editMeasure = $editMeasure['value'];
    else $editMeasure = $editMeasure['default'];
}

$u = new seTable('shop_measure_weight','smw');
$u->select('smw.*');
$result = $u->getList();

$items = array();
foreach($result as $item) {
    $item["value"] = number_format($item["value"], (int) $item["precision"]);
    $item["resultValue"] = "1 {$baseMeasure} = {$item["value"]} {$item["designation"]}";
    $item["isActive"] = (bool)$item["is_base"];
    if ($item["code"] == $viewMeasure)
        $item["isView"] = true;
    if ($item["code"] == $editMeasure)
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
