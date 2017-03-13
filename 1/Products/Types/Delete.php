<?php

if ($json->ids) {
    $ids = implode(",", $json->ids);
    $u = new seTable('shop_product_type','spt');
    $u->where('id in (?)', $ids)->deletelist();
}

$status = array();
if (!se_db_error()) {
    $status['status'] = 'ok';
} else {
    $status['status'] = 'error';
    $status['error'] = 'Не удаётся удалить типы товаров!';
}

outputData($status);