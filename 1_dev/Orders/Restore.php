<?php

$idsStr = implode(",", $json->ids);
if ($json->ids) {
    se_db_query("UPDATE shop_order SET is_delete = 'N' WHERE id IN ({$idsStr})");
}


if (!se_db_error()) {
    $status['status'] = 'ok';
    $status['data'] = $order;
} else {
    $status['status'] = 'error';
    $status['errortext'] = 'Не удаётся восстановить заказы!';
}
outputData($status);