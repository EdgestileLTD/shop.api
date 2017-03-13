<?php

if ($json->ids) {
    $ids = implode(",", $json->ids);
    $u = new seTable('company');
    $u->where('id in (?)', $ids)->deletelist();
}

$status = array();
if (!se_db_error()) {
    $status['status'] = 'ok';
} else {
    $status['status'] = 'error';
    $status['error'] = 'Не удается произвести удаление!';
}

outputData($status);