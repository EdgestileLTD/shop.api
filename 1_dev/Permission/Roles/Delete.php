<?php

if ($json->ids) {
    $ids = implode(",", $json->ids);
    $u = new seTable('permission_role');
    $u->where('id in (?)', $ids)->deletelist();
}

$status = array();
if (!se_db_error()) {
    $status['status'] = 'ok';
} else {
    $status['status'] = 'error';
    $status['error'] = 'Не удаётся удалить роль!';
}

outputData($status);