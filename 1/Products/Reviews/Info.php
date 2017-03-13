<?php

if (empty($json->ids))
    $json->ids[] = $_GET['id'];
$ids = implode(",", $json->ids);

$u = new seTable('shop_reviews','sr');
$u->select('sr.*, CONCAT_WS(" ", p.last_name, p.first_name, p.sec_name) name_user, sp.name name_product');
$u->innerjoin('person p','p.id = sr.id_user');
$u->innerjoin('shop_price sp', 'sp.id = sr.id_price');
$u->where("sr.id in ($ids)");
$u->groupby('id');

$result = $u->getList();

foreach($result as $item) {
    $review = null;
    $review['id'] = $item['id'];
    $review['idProduct'] = $item['id_price'];
    $review['idUser'] = $item['id_user'];
    $review['nameProduct'] = $item['name_product'];
    $review['nameUser'] = $item['name_user'];
    $review['mark'] = (int) $item['mark'];
    $review['merits'] = $item['merits'];
    $review['demerits'] = $item['demerits'];
    $review['comment'] = $item['comment'];
    $review['useTime'] = (int) $item['use_time'];
    $review['dateTime'] = $item['date'];
    $review['dateTimeTitle'] = date("H:i d.m.Y", strtotime($item['date']));
    $review['countLikes'] = (int) $item['likes'];
    $review['countDislikes'] = (int) $item['dislikes'];
    $review['isActive'] = (bool) $item['active'];
    $items[] = $review;
}

$data['count'] = $count;
$data['items'] = $items;

$status = array();
if (!se_db_error()) {
    $status['status'] = 'ok';
    $status['data'] = $data;
} else {
    $status['status'] = 'error';
    $status['error'] = 'Не удаётся получить информацию об отзыве!';
}
outputData($status);