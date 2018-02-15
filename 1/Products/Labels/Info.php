<?php

if (empty($json->ids))
    $json->ids[] = $_GET['id'];
$ids = implode(",", $json->ids);

$u = new seTable('shop_label', 'sl');
$u->where("sl.id in ($ids)");
$result = $u->getList();

$status = array();
$items = array();

foreach ($result as $item) {
    $label = null;
    $label['id'] = $item['id'];
    $label['code'] = $item['code'];
    $label['name'] = $item['name'];
    $label['imageFile'] = $item['image'];
    if ($label['imageFile']) {
        if (strpos($label['imageFile'], "://") === false) {
            $label['imageUrl'] = 'http://' . $json->hostname . "/images/rus/labels/" . $label['imageFile'];
            $label['imageUrlPreview'] = "http://{$json->hostname}/lib/image.php?size=64&img=images/rus/labels/" . $label['imageFile'];
        } else {
            $label['imageUrl'] = $label['imageFile'];
            $label['imageUrlPreview'] = $label['imageFile'];
        }
    }
    $items[] = $label;
}

$data['count'] = sizeof($items);
$data['items'] = $items;

if (se_db_error()) {
    $status['status'] = 'error';
    $status['error'] = 'Не удаётся получить информацию о ярлыке!';
} else {
    $status['status'] = 'ok';
    $status['data'] = $data;
}

outputData($status);