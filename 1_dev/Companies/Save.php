<?php

$ids = array();
if (empty($json->ids) && !empty($json->id))
    $ids[] = $json->id;
else $ids = $json->ids;
$isNew = empty($ids);
if (!$isNew)
    $idsStr = implode(",", $ids);

function existINN($inn, $id)
{
    $u = new seTable('company', 'c');
    $u->where("inn = '?'", $inn);
    if ($id)
        $u->andWhere("id <> ?", $id);
    $u->fetchOne();
    return $u->isFind();
}

if ($json->inn && existINN($json->inn, $ids[0])) {
    $status['status'] = 'error';
    $status['errortext'] = 'Компания с указанным ИНН уже существует!';
    outputData($status);
    exit;
}


if ($isNew || !empty($ids)) {
    $u = new seTable('company', 'c');
    $isUpdated = false;
    $isUpdated |= setField($isNew, $u, $json->name, 'name');
    $isUpdated |= setField($isNew, $u, $json->inn, 'inn');
    $isUpdated |= setField($isNew, $u, $json->phone, 'phone');
    $isUpdated |= setField($isNew, $u, $json->email, 'email');
    $isUpdated |= setField($isNew, $u, $json->address, 'address');
    $isUpdated |= setField($isNew, $u, $json->note, 'note');

    if ($isUpdated) {
        if (!empty($idsStr))
            $u->where('id in (?)', $idsStr);
        $ids[0] = $u->save();
    }
}

$data['id'] = $ids[0];
$status = array();

if (!se_db_error()) {
    $status['status'] = 'ok';
    $status['data'] = $data;
} else {
    $status['status'] = 'error';
    $status['errortext'] = 'Не удаётся сохранить компанию!';
}

outputData($status);
