<?php

if ($json->ids) {
    $ids = implode(",", $json->ids);
    $u = new seTable('shop_contacts','sc');
    $u->where('id IN (?)', $ids)->deletelist();
}

$status = array();
if (!se_db_error()) {
    $status['status'] = 'ok';
} else {
    $status['status'] = 'error';
    $status['errortext'] = 'Не удаётся удалить контакт для геотаргетинга!';
}

outputData($status);