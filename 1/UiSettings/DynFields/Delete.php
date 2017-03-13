<?php

if ($json->ids) {
    $ids = implode(",", $json->ids);
    $u = new seTable('shop_userfields', 'suf');
    $u->where('id in (?)', $ids)->deletelist();
}

$status = array();
if (!se_db_error()) {
    $status['status'] = 'ok';
} else {
    $status['status'] = 'error';
    $status['error'] = 'Не удаётся удалить поле! Возможно данное поле используется в заказах!';
}

outputData($status);
