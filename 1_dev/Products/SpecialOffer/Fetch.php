<?php

    function convertFields($str) {
        $str = str_replace('id ', 'sp.id ', $str);
        return $str;
    }

    $u = new seTable('shop_leader','sl');
    $u->select('sp.id, sl.lang, sp.article, sp.code, sp.name, sp.price, sp.curr');
    $u->innerjoin('shop_price sp', 'sp.id = sl.id_price');

    $patterns = array('id'=>'sp.id',
        'code'=>'sp.code',
        'article'=>'sp.article',
        'name'=>'sp.name',
        'price'=>'sp.price'
    );

    $sortBy = (isset($patterns[$json->sortBy])) ? $patterns[$json->sortBy] : 'id';
    $u->orderby($sortBy, $json->sortOrder === 'desc');

    $objects = $u->getList();
    foreach($objects as $item) {
        $product = null;
        $product['id'] = $item['id'];
        $product['name'] = $item['name'];
        $product['article'] = $item['article'];
        $product['code'] = $item['code'];
        $product['currency'] = $item['curr'];
        $product['price'] = (real) $item['price'];
        $items[] = $product;
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