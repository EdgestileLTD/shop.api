<?php

$ids = array();
if (empty($json->ids) && !empty($json->id))
    $ids[] = $json->id;
else $ids = $json->ids;
$isNew = empty($ids);
if (!$isNew)
    $idsStr = implode(",", $ids);

$u = new seTable('shop_status', 'ss');

if ($isNew || !empty($ids)) {
    $isUpdated = false;
    $isUpdated |= setField($isNew, $u, $json->name, 'name');
    if (!empty($json->color) && strpos($json->color, "#"))
        $json->color = str_replace("#", "", $json->color);
    $isUpdated |= setField($isNew, $u, $json->color, 'color');
    $isUpdated |= setField($isNew, $u, $json->note, 'note');

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
    $status['errortext'] = 'Не удаётся сохранить информация о статусе заказа!';
}

outputData($status);
