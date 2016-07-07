<?php

if ($json->ids) {
    $ids = implode(",", $json->ids);
    $u = new seTable('shop_product_type','spt');
    $u->where('id IN (?) AND code IS NULL', $ids)->deletelist();
}

$status = array();
if (!se_db_error()) {
    $status['status'] = 'ok';
} else {
    $status['status'] = 'error';
    $status['errortext'] = 'Не удаётся удалить статусы заказов! Возможно существуют заказы с указанными статусами!';
}

outputData($status);