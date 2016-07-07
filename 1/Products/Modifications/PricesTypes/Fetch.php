<?php
    $status = array();

    $items[0]["id"] = "0";
    $items[0]["name"] = "Добавляет к цене";
    $items[1]["id"] = "1";
    $items[1]["name"] = "Умножает на цену";
    $items[2]["id"] = "2";
    $items[2]["name"] = "Замещает цену";

    $status['status'] = 'ok';
    $status['data']['items'] = $items;

    outputData($status);