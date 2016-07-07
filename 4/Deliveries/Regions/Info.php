<?php

    if (empty($json->ids))
        $json->ids[] = $_GET['id'];
    $ids = implode(",", $json->ids);

    $u = new seTable('shop_deliverytype', 'sdt');
    $u->select("sdt.*, GROUP_CONCAT(sdr.id_country) AS id_country,
    GROUP_CONCAT(sdr.id_region) AS id_region,
    GROUP_CONCAT(sdr.id_city) AS id_city");
    $u->leftjoin('shop_delivery_region sdr', 'sdt.id=sdr.id_delivery');
    $u->where('sdt.id IN (?)', $ids);
    $u->groupby('sdt.id');
    //$u->andwhere('sdt.upid IS NOT NULL');

    //$u = new seTable('shop_delivery_region');
    //$u->where('id in (?)', $ids);
    $objects = $u->getList();
    foreach($objects as $item) {
        $delivery = null;
        $delivery['id'] = $item['id'];
        $delivery['regions'] = array();
        $delivery['regions']['idCountry'] = explode(',', $item['id_country']);
        $delivery['regions']['idRegion'] = explode(',', $item['id_region']);
        $delivery['regions']['idCity'] = explode(',', $item['id_city']);
        //$delivery['idCountry'] = $item['id_country'];
        //$delivery['idRegin'] = $item['id_region'];
        //$delivery['idCity'] = $item['id_city'];
        $delivery['price'] = (float) $item['price'];
        $delivery['volumeMax'] = (float)$item['max_volume'];
        $delivery['weightMax'] = $item['max_weight'];
        $delivery['period'] = $item['time'];
        $delivery['addr'] = $item['note'];
        $delivery['isActive'] = ($item['status']=='Y');
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
        $status['errortext'] = se_db_error();
    }
    outputData($status);

