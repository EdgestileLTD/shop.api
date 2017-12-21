<?php

$ids = array();
if (empty($json->ids) && !empty($json->id))
    $ids[] = $json->id;
else $ids = $json->ids;
$isNew = empty($ids);
if (!$isNew)
    $idsStr = implode(",", $ids);

$u = new seTable('shop_preorder', 'sp');

if ($isNew || !empty($ids)) {

    $isUpdated = false;
    $isUpdated |= setField($isNew, $u, $json->customer, 'name');
    $isUpdated |= setField($isNew, $u, $json->customerPhone, 'phone');
    $isUpdated |= setField($isNew, $u, $json->customerEmail, 'email');
    $isUpdated |= setField($isNew, $u, $json->count, 'count');
    $isUpdated |= setField($isNew, $u, $json->idProduct, 'id_price');

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
}

$data['id'] = $ids[0];
$status = array();
if (!se_db_error()) {
    $status['status'] = 'ok';
    $status['data'] = $data;
} else {
    $status['status'] = 'error';
    $status['error'] = se_db_error();
}

outputData($status);