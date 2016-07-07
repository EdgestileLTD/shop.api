<?php

$ids = array();
if (empty($json->ids) && !empty($json->id))
    $ids[] = $json->id;
else $ids = $json->ids;
$isNew = empty($ids);
if (!$isNew)
    $idsStr = implode(",", $ids);

if ($isNew || !empty($ids)) {

    $u = new seTable('shop_variables','sv');

    $isUpdated = false;
    $isUpdated |= setField($isNew, $u, $json->name, 'name');
    $isUpdated |= setField($isNew, $u, $json->value, 'value');

    if ($isUpdated){
        if (!empty($idsStr))
            $u->where('id in (?)', $idsStr);
        $idNew = $u->save();
    }
    if ($isNew)
        $id = $idNew;
    else $id = $ids[0];
}

$data['id'] = $id;
$status = array();
if (!se_db_error()) {
    $status['status'] = 'ok';
    $status['data'] = $data;
} else {
    $status['status'] = 'error';
    $status['errortext'] = 'Не удаётся сохранить SEO переменную!';
}

outputData($status);