<?php

    if (empty($json->ids) && !empty($json->id))
        $json->ids[] = $json->id;
    $ids = implode(",", $json->ids);

    $v = new seTable('shop_mail','sm');
    $v->select('sm.*');
    $v->where("id IN (?)", $ids);
    $objects = $v->getList();

    $items = array();
    foreach($objects as $item) {
        $letter = null;
        $letter['id'] = $item['id'];
        $letter['idGroup'] = $item['shop_mail_group_id'];
        $letter['name'] = $item['title'];
        $letter['code'] = $item['mailtype'];
        $letter['subject'] = $item['subject'];
        $letter['letter'] = $item['letter'];
        $letter['sortIndex'] = $item['itempost'];
        $items[] = $letter;
    }

    $data['count'] = sizeof($items);
    $data['items'] = $items;

    if (se_db_error()) {
        $status['status'] = 'error';
        $status['error'] = se_db_error();
    } else {
        $status['status'] = 'ok';
        $status['data'] = $data;
    }

    outputData($status);
