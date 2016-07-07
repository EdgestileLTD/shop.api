<?php
    $u = new seTable('shop_mail_group','smg');
    $u->select('smg.*');
    $u->orderby('id');
    $items = $u->getList();

    $groups = array();
    foreach($items as $item) {
        $group = null;
        $group['id'] = $item['id'];
        $group['name'] = $item['name'];
        $groups[] = $group;
    }
    $data['count'] = sizeof($items);
    $data['items'] = $groups;

    $status = array();
    if (!mysql_error()) {
        $status['status'] = 'ok';
        $status['data'] = $data;
    } else {
        $status['status'] = 'error';
        $status['errortext'] = mysql_error();
    }
    outputData($status);