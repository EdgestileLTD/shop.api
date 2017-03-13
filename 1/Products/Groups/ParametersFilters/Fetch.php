<?php
    $u = new seTable('shop_feature','sf');
    $u->select('id, name, sort');
    $u->orderby('sf.sort, sf.name');

    $item['code'] = 'price';
    $item['name'] = 'Цена';
    $items[] = $item;
    $item['code'] = 'brand';
    $item['name'] = 'Бренды';
    $items[] = $item;
    $item['code'] = 'flag_hit';
    $item['name'] = 'Хиты';
    $items[] = $item;
    $item['code'] = 'flag_new';
    $item['name'] = 'Новинки';
    $items[] = $item;

    $objects = $u->getList();
    foreach($objects as $item) {
        $filter = null;
        $filter['id'] = $item['id'];
        $filter['name'] = $item['name'];
        $items[] = $filter;
    }

    $data['count'] = sizeof($objects) + 4;
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