<?php

function checkReviewParams($ids, $idProduct, $idUser) {
    $u = new seTable('shop_reviews','sr');
    $u->select("id");
    $u->where("id_price = ?", $idProduct);
    $u->andWhere("id_user = ?", $idUser);
    if ($ids)
        $u->andWhere("NOT id IN (?)", $ids);
    $items = $u->getList();

    if ($items) {
        $status['status'] = 'error';
        $status['errortext'] = "Отзыв выбранного покупателя для данного товара уже существует!";
        outputData($status);
        exit;
    }
}

$ids = array();
if (empty($json->ids) && !empty($json->id))
    $ids[] = $json->id;
else $ids = $json->ids;
$isNew = empty($ids);
if (!$isNew)
    $idsStr = implode(",", $ids);

$u = new seTable('shop_reviews','sr');

if ($isNew || !empty($ids)) {

    if (isset($json->idProduct) && isset($json->idUser))
        checkReviewParams($idsStr, $json->idProduct, $json->idUser);

    $isUpdated = false;
    $isUpdated |= setField($isNew, $u, $json->isActive, 'active');
    $isUpdated |= setField($isNew, $u, $json->idProduct, 'id_price');
    $isUpdated |= setField($isNew, $u, $json->idUser, 'id_user');
    $isUpdated |= setField($isNew, $u, $json->mark, 'mark');
    $isUpdated |= setField($isNew, $u, $json->merits, 'merits');
    $isUpdated |= setField($isNew, $u, $json->demerits, 'demerits');
    $isUpdated |= setField($isNew, $u, $json->comment, 'comment');
    $isUpdated |= setField($isNew, $u, $json->useTime, 'use_time');
    $isUpdated |= setField($isNew, $u, $json->dateTime, 'date');
    $isUpdated |= setField($isNew, $u, $json->countLikes, 'likes');
    $isUpdated |= setField($isNew, $u, $json->countDislikes, 'dislikes');

    if ($isUpdated){
        if (!empty($idsStr))
            $u->where('id in (?)', $idsStr);
        $idv = $u->save();
        if ($isNew)
            $ids[] = $idv;
    }
}

$data['id'] = $ids[0];
$status = array();
if (!se_db_error()) {
    $status['status'] = 'ok';
    $status['data'] = $data;
} else {
    $status['status'] = 'error';
    $status['errortext'] = se_db_error();
}

outputData($status);
