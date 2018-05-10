<?php
if (empty($json->ids))
    $json->ids[] = $_GET['id'];
$ids = implode(",", $json->ids);

$u = new seTable('shop_feature_value_list','sfl');
$u->where('sfl.id in (?)', $ids);
$result = $u->getList();

$items = array();
foreach($result as $item) {
    $value = null;
    $value['id'] = $item['id'];
    $value['idFeature'] = $item['id_feature'];
    $value['color'] = $item['color'];
    $value['name'] = $item['value'];
    $value['code'] = $item['code'];
    $value['imageFile'] = $item['image'];
    $value['sortIndex'] = (int) $item['sort'];
    $value['isDefault'] = (bool) $item['default'];
    $items[] = $value;
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