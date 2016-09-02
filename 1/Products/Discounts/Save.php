<?php

function saveItems($idsDiscounts, $items, $typeItem)
{
    if ($typeItem == 1)
        $field = 'id_price';
    if ($typeItem == 2)
        $field = 'id_group';
    if ($typeItem == 3)
        $field = 'id_modification';
    if ($typeItem == 4)
        $field = 'id_user';
    $idsStr = implode(",", $idsDiscounts);
    $u = new seTable('shop_discount_links','sdl');
    $u->where("discount_id in (?) AND $field IS NOT NULL", $idsStr)->deletelist();

    $i = se_db_insert_id('shop_modifications');
    foreach ($items as $item) {
        foreach($idsDiscounts as $idDiscount)
            $data[] = array('discount_id' => $idDiscount, $field => $item->id);
    }
    if (!empty($data))
        se_db_InsertList('shop_discount_links', $data);
}


$ids = array();
if (empty($json->ids) && !empty($json->id))
    $ids[] = $json->id;
else $ids = $json->ids;
$isNew = empty($ids);
if (!$isNew)
    $idsStr = implode(",", $ids);

$u = new seTable('shop_discounts', 'sd');

if ($isNew || !empty($ids)) {
    $isUpdated = false;
    $isUpdated |= setField($isNew, $u, $json->name, 'title');
    $isUpdated |= setField($isNew, $u, $json->stepTime, 'step_time');
    $isUpdated |= setField($isNew, $u, $json->stepDiscount, 'step_discount');
    if (!$json->isDateTimeFrom && !$isNew)
        $json->dateTimeFrom = 0;
    if (!$json->isDateTimeTo && !$isNew)
        $json->dateTimeTo = 0;
    $isUpdated |= setField($isNew, $u, $json->dateTimeFrom, 'date_from');
    $isUpdated |= setField($isNew, $u, $json->dateTimeTo, 'date_to');
    $isUpdated |= setField($isNew, $u, $json->week, 'week');
    $isUpdated |= setField($isNew, $u, $json->sumFrom, 'summ_from');
    $isUpdated |= setField($isNew, $u, $json->sumTo, 'summ_to');
    $isUpdated |= setField($isNew, $u, $json->countFrom, 'count_from');
    $isUpdated |= setField($isNew, $u, $json->countTo, 'count_to');
    $isUpdated |= setField($isNew, $u, $json->discount, 'discount');
    $isUpdated |= setField($isNew, $u, $json->typeDiscount, 'type_discount');
    $isUpdated |= setField($isNew, $u, $json->typeSum, 'summ_type');
    $isUpdated |= setField($isNew, $u, $json->customerType, 'customer_type');
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
    if ($ids && isset($json->listProducts))
        saveItems($ids, $json->listProducts, 1);
    if ($ids && isset($json->listGroupsProducts))
        saveItems($ids, $json->listGroupsProducts, 2);
    if ($ids && isset($json->listModificationsProducts))
        saveItems($ids, $json->listModificationsProducts, 3);
    if ($ids && isset($json->listContacts))
        saveItems($ids, $json->listContacts, 4);
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