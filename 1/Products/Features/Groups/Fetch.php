<?php
    $u = new seTable('shop_feature_group','sfg');
    $u->select('sfg.*');
    $u->orderby('sort');

    $objects = $u->getList();
    foreach($objects as $item) {
        $group = null;
        $group['id'] = $item['id'];
        $group['name'] = $item['name'];
        $group['description'] = $item['description'];
        $group['imageFile'] = $item['image'];
        $group['sortIndex'] = (int) $item['sort'];
        $items[] = $group;
    }

    $data['count'] = sizeof($items);
    $data['items'] = $items;

    $status = array();
    if (!se_db_error()) {
        $status['status'] = 'ok';
        $status['data'] = $data;
    } else {
        $status['status'] = 'error';
        $status['error'] = se_db_error();
    }

    outputData($status);
