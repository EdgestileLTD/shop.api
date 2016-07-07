<?php

if ($json->ids) {
    $ids = implode(",", $json->ids);
    $u = new seTable('shop_section_item','ssi');
    $u->where('id in (?)', $ids)->deletelist();
}

$status = array();
if (!se_db_error()) {
    $status['status'] = 'ok';
} else {
    $status['status'] = 'error';
    $status['errortext'] = 'Не удаётся удалить элемент раздела!';
}

outputData($status);