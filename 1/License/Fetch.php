<?php

    class Data
    {
        public $items = array();
    }

    class Delivery
    {
        public $id;
        public $serial;
        public $regKey;
        public $dateReg;
    }


    $u = new seTable('shop_license','sd');
    $u->select('sd.*');
    $objects = $u->getList();
    foreach($objects as $item) {
        $delivery = new Delivery();
        $delivery->id = (int) $item['id'];
        $delivery->serial = $item['serial'];
        $delivery->regKey =  $item['regkey'];
        $delivery->dateReg = $item['datereg'];
        $items[] = $delivery;
    }

    $data = new Data();
    $data->count = $count;
    $data->items = $items;

    $status = array();
    if (!mysql_error()) {
        $status['status'] = 'ok';
        $status['data'] = $data;
    } else {
        $status['status'] = 'error';
        $status['errortext'] = mysql_error();
    }
    echo json_encode($status);