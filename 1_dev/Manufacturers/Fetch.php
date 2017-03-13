<?php

    $u = new seTable('shop_price');
    $u->select('DISTINCT(`manufacturer`)');
    $u->where("manufacturer IS NOT NULL AND manufacturer <> ''");
    $objects = $u->getList();
    $count = 0;

    foreach($objects as $item) {
        $count++;
        $manufacturer = null;
        $manufacturer['name'] = $item['manufacturer'];
        $items[] = $manufacturer;
    }

    $data['count'] = $count;
    $data['items'] = $items;

    $status = array();
    if (!mysql_error()) {
        $status['status'] = 'ok';
        $status['data'] = $data;
    } else {
        $status['status'] = 'error';
        $status['error'] = mysql_error();
    }
    outputData($status);