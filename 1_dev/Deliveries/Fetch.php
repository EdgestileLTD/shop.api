<?php

    $u = new seTable('shop_deliverytype','sd');

    if (!$u->isFindField('id_parent')) {
        $u->addField('id_parent', 'int(10) default NULL', 1);
    }
    if (!$u->isFindField('sort')) {
        $u->addField('sort', 'int(11) default 0', 1);
    }

    $u->select('sd.*');
    $u->where('sd.id_parent IS NULL');
    $u->orderby('sort', false);

    $objects = $u->getList();
    foreach($objects as $item) {
        $delivery = null;
        $delivery['id'] = $item['id'];
        $delivery['code'] = strtolower($item['code']);
        if (empty($delivery['code']))
            $delivery['code'] = "simple";
        $delivery['name'] = $item['name'];
        $delivery['idParent'] = $item['id_parent'];
        $delivery['period'] = (int) $item['time'];
        $delivery['price'] = (float) $item['price'];
        $delivery['idCityFrom'] = $item['city_from_delivery'];
        $delivery['isActive'] = (bool) ($item['status'] == 'Y');
        $delivery['week'] = $item['week'];
        $delivery['currency'] = $item['curr'];
        $delivery['onePosition'] = (bool) ($item['forone'] == 'Y');
        $delivery['maxVolume'] = (int) $item['max_volume'];
        $delivery['maxWeight'] = (real) $item['max_weight'];
        $delivery['needAddress'] = (bool) ($item['need_address'] == 'Y');
        $delivery['note'] = $item['note'];
        $items[] = $delivery;
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

