<?php
if (empty($json->ids))
    $json->ids[] = $_GET['id'];
$ids = implode(",", $json->ids);

$u = new seTable('shop_brand', 'sb');
$u->where("sb.id in ($ids)");
$result = $u->getList();

$status = array();
$items = array();

foreach ($result as $item) {
    $brand = null;
    $brand['id'] = $item['id'];
    $brand['code'] = $item['code'];
    $brand['name'] = $item['name'];
    $brand['description'] = $item['text'];
    $brand['imageFile'] = $item['image'];
    $brand['seoHeader'] = $item['title'];
    $brand['seoKeywords'] = $item['keywords'];
    $brand['seoDescription'] = $item['description'];
    $items[] = $brand;
    if ($brand['imageFile']) {
        if (strpos($brand['imageFile'], "://") === false) {
            $brand['imageUrl'] = 'http://' . $json->hostname . "/images/rus/shopbrand/" . $brand['imageFile'];
            $brand['imageUrlPreview'] = "http://{$json->hostname}/lib/image.php?size=64&img=images/rus/shopbrand/" . $brand['imageFile'];
        } else {
            $brand['imageUrl'] = $brand['imageFile'];
            $brand['imageUrlPreview'] = $brand['imageFile'];
        }
    }
}

$data['count'] = sizeof($items);
$data['items'] = $items;

if (se_db_error()) {
    $status['status'] = 'error';
    $status['error'] = 'Не удаётся получить информацию о бренде!';
} else {
    $status['status'] = 'ok';
    $status['data'] = $data;
}

outputData($status);