<?php

    $u = new seTable('main','sd');
    $u->select('sd.*');

    $objects = $u->getList();
    foreach($objects as $item) {
        $lang = null;
        $lang->id = $item['id'];
        $lang->lang = $item['lang'];
        $items[] = $lang;
    }

    $data['count'] = sizeof($items);
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
