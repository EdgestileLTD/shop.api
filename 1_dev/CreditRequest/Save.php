<?php


$ids = array();
if (empty($json->ids) && !empty($json->id))
    $ids[] = $json->id;
else $ids = $json->ids;
$isNew = empty($ids);
if (!$isNew)
    $idsStr = implode(",", $ids);


function saveProducts($idsRequests, $products)
{

    $idsUpdate = '';
    foreach ($products as $p)
        if ($p->id) {
            if (!empty($idsUpdate))
                $idsUpdate .= ',';
            $idsUpdate .= $p->id;
        }

    $idsStr = implode(",", $idsRequests);


    $u = new seTable('shop_credit_item', 'sci');
    if (!empty($idsUpdate))
        $u->where('NOT `id` IN (' . $idsUpdate . ') AND id_credit in (?)', $idsStr)->deletelist();
    else $u->where('id_credit in (?)', $idsStr)->deletelist();

    // новые товары
    foreach ($products as $p) {
        if (!$p->id) {
            foreach ($idsRequests as $idRequest) {
                $u = new seTable('shop_credit_item', 'sci');
                setField(true, $u, $idRequest, 'id_credit');
                setField(true, $u, $p->idPrice, 'id_price');
                setField(true, $u, $p->article, 'article');
                setField(true, $u, $p->name, 'name');
                setField(true, $u, $p->price, 'price');
                setField(true, $u, $p->discount, 'discount');
                setField(true, $u, $p->count, 'count');
                setField(true, $u, $p->note, 'commentary');
                $u->save();
            }
        }
    }


    // обновление товаров
    foreach ($products as $p)
        if ($p->id) {
            $u = new seTable('shop_credit_item', 'sci');
            $isUpdated = false;
            setField(true, $u, $p->idPrice, 'id_price');
            setField(true, $u, $p->article, 'article');
            setField(true, $u, $p->name, 'name');
            setField(true, $u, $p->price, 'price');
            setField(true, $u, $p->discount, 'discount');
            setField(true, $u, $p->count, 'count');
            setField(true, $u, $p->note, 'commentary');
            $u->where('id=?', $p->id);
            if ($isUpdated)
                $u->save();
        }
}

$u = new seTable('shop_credit', 'sc');

if ($isNew || !empty($ids)) {

    if ($isNew) {
        $u->id = $ids[0];
        if (empty($json->dateOrder))
            $json->dateOrder = date("Y-m-d");
        $isUpdated |= setField($isNew, $u, $json->dateOrder, 'date');
    }

    $isUpdated = false;
    $isUpdated |= setField($isNew, $u, $json->customer, 'fio');
    $isUpdated |= setField($isNew, $u, $json->customerPhone, 'phone');
    $isUpdated |= setField($isNew, $u, $json->customerINN, 'inn');
    $isUpdated |= setField($isNew, $u, $json->note, 'commentary');

    if ($isUpdated) {
        if (!empty($idsStr)) {
            if ($idsStr != "all")
                $u->where('id in (?)', $idsStr);
            else $u->where('true');
        }
        $idv = $u->save();
        if ($isNew)
            $ids[] = $idv;
    }

    if ($ids && isset($json->items))
        saveProducts($ids, $json->items);
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