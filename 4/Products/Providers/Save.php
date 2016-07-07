<?php


$ids = array();
if (empty($json->ids) && !empty($json->id))
    $ids[] = $json->id;
else $ids = $json->ids;
$isNew = empty($ids);
if (!$isNew)
    $idsStr = implode(",", $ids);

$u = new seTable('shop_providers', 'sp');

if ($isNew || !empty($ids)) {
    $isUpdated = false;
    $isUpdated |= setField($isNew, $u, $json->name, 'name');
    $isUpdated |= setField($isNew, $u, $json->idUser, 'id_user');
    $isUpdated |= setField($isNew, $u, $json->address, 'address');
    $isUpdated |= setField($isNew, $u, $json->phone, 'phone');
    $isUpdated |= setField($isNew, $u, $json->email, 'email');
    $isUpdated |= setField($isNew, $u, $json->contact, 'contact');

    if ($isUpdated) {
        if (!empty($idsStr)) {
            if ($idsStr != "all")
                $u->where('id in (?)', $idsStr);
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
    $status['errortext'] = 'Не удаётся сохранить информация о поставщике!';
}

outputData($status);