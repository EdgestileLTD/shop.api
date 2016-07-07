<?php
$isNew = empty($json->id);

$u = new seTable('shop_comm','sc');

if (!$isNew)
    $u->find($json->id);


if ($isNew || $u->id) {
    $u->name = $json->contactTitle;
    if ($json->idProduct)
        $u->id_price = $json->idProduct;
    $u->email = $json->contactEmail;
    $u->commentary = $json->commentary;
    $u->response = $json->response;
    $u->date = $json->date;
    $u->mark = 0;

    if ($isNew)
        $id = $u->save();
    else {
        if ($u->save())
            $id = $json->id;
    }
} else $id = "";

$data['id'] = $id;
$status = array();
if (!se_db_error()) {
    $status['status'] = 'ok';
    $status['data'] = $data;
} else {
    $status['status'] = 'error';
    $status['errortext'] = "Не удаётся сохранить комментарий!";
}

outputData($status);
