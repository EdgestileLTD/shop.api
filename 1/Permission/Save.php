<?php

$ids = array();
if (empty($json->ids) && !empty($json->id))
    $ids[] = $json->id;
else $ids = $json->ids;
$isNew = empty($ids);
if (!$isNew)
    $idsStr = implode(",", $ids);

$u = new seTable('permission_object_role', 'por');

if ($isNew || !empty($ids)) {
    $isUpdated = false;
    $isUpdated |= setField($isNew, $u, $json->mask, 'mask');
    if ($isNew) {
        $isUpdated |= setField($isNew, $u, $json->idObject, 'id_object');
        $isUpdated |= setField($isNew, $u, $json->idRole, 'id_role');
    }

    if ($isUpdated) {
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
    $status['error'] = 'Не удаётся сохранить настройки прав!';
}

outputData($status);
