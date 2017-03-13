<?php
if ($json->ids) {
    $ids = implode(",", $json->ids);
    $u = new seTable('shop_price', 'sp');
    if (strpos($ids, "*") === false)
        $u->where('id IN (?)', $ids)->deletelist();
    else {
        if (!empty($json->filter)) {

        } else $u->where('TRUE')->deletelist();
    }
}

$status = array();
if (!se_db_error()) {
    $status['status'] = 'ok';
} else {
    $status['status'] = 'error';
    $status['error'] = 'Не удаётся удалить товары!';
}

outputData($status);