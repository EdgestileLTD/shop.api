<?php
$idFeature = $json->idFeature;

$u = new seTable('shop_feature_value_list', 'sfl');
if ($idFeature)
    $u->where('id_feature=?', $idFeature);
$u->orderby('sort, sfl.value');

if (empty($idFeature))
    $objects = $u->getList(0, 1000);
else $objects = $u->getList();

foreach ($objects as $item) {
    $value = null;
    $value->id = $item['id'];
    $value->idFeature = $item['id_feature'];
    $value->name = $item['value'];
    $value->color = $item['color'];
    $value->sortIndex = (int)$item['sort'];
    $value->isDefault = (bool)$item['default'];
    $items[] = $value;
}

$data['count'] = sizeof($objects);
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