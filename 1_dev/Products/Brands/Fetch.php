<?php

$u = new seTable('shop_brand', 'sb');
$u->select('sb.*, COUNT(sp.id) countGoods');
$u->leftjoin('shop_price sp', 'sb.id=sp.id_brand');

$searchStr = $json->searchText;
$searchArr = explode(' ', $searchStr);

if (!empty($searchStr)) {
    foreach ($searchArr as $searchItem) {
        if (!empty($search))
            $search .= " AND ";
        $search .= "(sb.code LIKE '%$searchItem%' OR sb.name LIKE '%$searchItem%')";
    }
    $u->where($search);
}

$patterns = array('id' => 'sb.id',
    'code' => 'sb.code',
    'article' => 'sp.article',
    'name' => 'sb.name'
);

$sortBy = (isset($patterns[$json->sortBy])) ? $patterns[$json->sortBy] : 'id';

if ($json->sortOrder == 'desc')
    $u->orderby($sortBy, 1);
else $u->orderby($sortBy, 0);
$u->groupby('sb.id');

$objects = $u->getList();
foreach ($objects as $item) {
    $brand = null;
    $brand['id'] = $item['id'];
    $brand['code'] = $item['code'];
    $brand['name'] = $item['name'];
    $brand['title'] = $item['name'];
    $brand['imageFile'] = $item['image'];
    $brand['description'] = $item['text'];
    $brand['fullDescription'] = $item['content'];
    $brand['seoHeader'] = $item['title'];
    $brand['seoKeywords'] = $item['keywords'];
    $brand['seoDescription'] = $item['description'];
    $brand['countGoods'] = (int)$item['countGoods'];
    if ($brand['imageFile']) {
        if (strpos($brand['imageFile'], "://") === false) {
            $brand['imageUrl'] = 'http://' . $json->hostname . "/images/rus/shopbrand/" . $brand['imageFile'];
            $brand['imageUrlPreview'] = "http://{$json->hostname}/lib/image.php?size=64&img=images/rus/shopbrand/" . $brand['imageFile'];
        } else {
            $brand['imageUrl'] = $brand['imageFile'];
            $brand['imageUrlPreview'] = $brand['imageFile'];
        }
    }
    $items[] = $brand;
}

$data['count'] = sizeof($items);
$data['items'] = $items;

$status = array();
if (!se_db_error()) {
    $status['status'] = 'ok';
    $status['data'] = $data;
} else {
    $status['status'] = 'error';
    $status['error'] = 'Не удаётся получить список брендов!';
}
outputData($status);