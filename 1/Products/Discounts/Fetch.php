<?php

    // преоброзование перемнных запроса в перемнные БД
    function convertFields($str) {
        $str = str_replace('idProduct', 'sdl.id_price', $str);
        $str = str_replace('idGroupProduct', 'sdl.id_group', $str);
        $str = str_replace('idModification', 'sdl.id_modification', $str);
        $str = str_replace('idUser', 'sdl.id_user', $str);
        return $str;
    }
    if (!empty($json->filter))
        $filter = convertFields($json->filter);

    $u = new seTable('shop_discounts','sd');
    $u->select('sd.*');
    if (!empty($filter)) {
        $u->innerjoin('shop_discount_links sdl', 'sdl.discount_id=sd.id');
        $u->where($filter);
    }
    $u->orderby("sd.sort");

    $objects = $u->getList();
    foreach($objects as $item) {
        $discount = null;
        $discount['id'] = $item['id'];
        $discount['name'] = $item['title'];
        $discount['stepTime'] = (int) $item['step_time'];
        $discount['stepDiscount'] = (float) $item['step_discount'];
        $discount['dateTimeFrom'] = $item['date_from'];
        $discount['dateTimeTo'] = $item['date_to'];
        $discount['week'] = $item['week'];
        $discount['sumFrom'] = (float) $item['summ_from'];
        $discount['sumTo'] = (float) $item['summ_to'];
        $discount['countFrom'] = (float) $item['count_from'];
        if  ($discount['countFrom'] < 0)
            $discount['countFrom'] = 0;
        $discount['countTo'] = (float) $item['count_to'];
        if  ($discount['countTo'] < 0)
            $discount['countTo'] = 0;
        $discount['discount'] = (float) $item['discount'];
        $discount['typeDiscount'] = $item['type_discount'];
        $discount['typeSum'] = $item['summ_type'];
        $items[] = $discount;
    }

    $data['count'] = sizeof($items);
    $data['items'] = $items;

    $status = array();
    if (!se_db_error()) {
        $status['status'] = 'ok';
        $status['data'] = $data;
    } else {
        $status['status'] = 'error';
        $status['errortext'] = se_db_error();
    }
    outputData($status);