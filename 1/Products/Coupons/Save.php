<?php

function saveProducts($ids, $products) {

    $idsStr = implode(",", $ids);
    $u = new seTable('shop_coupons_goods','scg');
    $u->where("price_id IS NOT NULL AND coupon_id in (?)", $idsStr)->deletelist();

    foreach ($products as $product) {
        foreach($ids as $id)
            $data[] = array('coupon_id' => $id, 'price_id' => $product->id);
    }
    if (!empty($data))
        se_db_InsertList('shop_coupons_goods', $data);
}

function saveGroups($ids, $groups) {

    $idsStr = implode(",", $ids);
    $u = new seTable('shop_coupons_goods','scg');
    $u->where("group_id IS NOT NULL AND coupon_id in (?)", $idsStr)->deletelist();

    foreach ($groups as $product) {
        foreach($ids as $id)
            $data[] = array('coupon_id' => $id, 'group_id' => $product->id);
    }
    if (!empty($data))
        se_db_InsertList('shop_coupons_goods', $data);
}

$ids = array();
if (empty($json->ids) && !empty($json->id))
    $ids[] = $json->id;
else $ids = $json->ids;
$isNew = empty($ids);
if (!$isNew)
    $idsStr = implode(",", $ids);

$u = new seTable('shop_coupons', 'sc');

if ($isNew || !empty($ids)) {
    $isUpdated = false;
    $isUpdated |= setField($isNew, $u, $json->code, 'code');
    $isUpdated |= setField($isNew, $u, $json->type, 'type');
    $isUpdated |= setField($isNew, $u, $json->discount, 'discount');
    $isUpdated |= setField($isNew, $u, $json->currencyCode, 'currency');
    $isUpdated |= setField($isNew, $u, $json->timeEnd, 'expire_date');
    $isUpdated |= setField($isNew, $u, $json->minSum, 'min_sum_order');
    $isUpdated |= setField($isNew, $u, $json->idUser, 'id_user');
    if ($json->isActive)
        $isUpdated |= setField($isNew, $u, 'Y', 'status');
    else $isUpdated |= setField($isNew, $u, 'N', 'status');
    if ($json->isRegUser)
        $isUpdated |= setField($isNew, $u, 'Y', 'only_registered');
    else $isUpdated |= setField($isNew, $u, 'N', 'only_registered');
    $isUpdated |= setField($isNew, $u, $json->count, 'count_used');

    if ($isUpdated){
        if (!empty($idsStr)) {
            if ($idsStr != "all")
                $u->where('id in (?)', $idsStr);
            else $u->where('true');
        }
        $idv = $u->save();
        if ($isNew)
            $ids[] = $idv;
    }

    if ($ids && isset($json->products))
        saveProducts($ids, $json->products);
    if ($ids && isset($json->groups))
        saveGroups($ids, $json->groups);
}

$data['id'] = $ids[0];
$status = array();
if (!se_db_error()) {
    $status['status'] = 'ok';
    $status['data'] = $data;
} else {
    $status['status'] = 'error';
    $status['error'] = 'Не удаётся сохранить купон!';
}

outputData($status);