<?php

$u = new seTable('shop_label', 'sl');
$u->select("sl.*");

$searchStr = $json->searchText;
$searchArr = explode(' ', $searchStr);

if (!empty($searchStr)) {
    foreach ($searchArr as $searchItem) {
        if (!empty($search))
            $search .= " AND ";
        $search .= "(sl.code LIKE '%$searchItem%' OR sl.name LIKE '%$searchItem%')";
    }
    $u->where($search);
}

$patterns = array('id' => 'sl.id',
    'code' => 'sl.code',
    'name' => 'sl.name'
);

$u->orderby("sl.sort");
$u->groupby('sl.id');

$objects = $u->getList();
foreach ($objects as $item) {
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

$status = array();
if (!se_db_error()) {
    $status['status'] = 'ok';
    $status['data'] = $data;
} else {
    $status['status'] = 'error';
    $status['error'] = 'Не удаётся получить список ярлык!';
}
outputData($status);