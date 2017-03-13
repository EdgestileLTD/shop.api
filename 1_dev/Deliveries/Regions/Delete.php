<?php

    if ($json->ids) {
        $ids = implode(",", $json->ids);
        $u = new seTable('shop_delivery_region');
        $u->where('id_delivery in (?)', $ids)->deletelist();

        $u = new seTable('shop_deliverytype');
        $u->where('id in (?)', $ids)->deletelist();
    }

    $status = array();
    if (!se_db_error()) {
        $status['status'] = 'ok';
    } else {
        $status['status'] = 'error';
        $status['error'] = se_db_error();
    }

    outputData($status);