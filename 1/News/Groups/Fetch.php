<?php
    $u = new seTable('news_category','nc');
    $u->orderby('sort');
    $u->addorderby('id');

    $objects = $u->getList();
    foreach($objects as $item) {
        $group = null;
        $group['id'] = $item['id'];
        $group['idParent'] = $item['parent_id'];
        $group['code'] = $item['ident'];
        $group['name'] = $item['title'];
        $group['sortIndex'] = (int) $item['sort'];
        $items[] = $group;
    }

    $data['count'] = sizeof($objects);
    $data['items'] = $items;

    $status = array();
    if (!mysql_error()) {
        $status['status'] = 'ok';
        $status['data'] = $data;
    } else {
        $status['status'] = 'error';
        $status['errortext'] = mysql_error();
    }

    outputData($status);