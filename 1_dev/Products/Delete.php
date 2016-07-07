<?php
    if ($json->ids) {
        $ids = implode(",", $json->ids);
        $u = new seTable('shop_price','sp');
        $u->where('id in (?)', $ids)->deletelist();
    }

    $status = array();
    if (!se_db_error()) {
        $status['status'] = 'ok';
    } else {
        $status['status'] = 'error';
        $status['errortext'] = se_db_error();
    }

    outputData($status);