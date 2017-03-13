<?php

    $u = new seTable('user_rekv_type', 'urt');
    $u->select('DISTINCT(urt.code), urt.size, urt.title');
    $result = $u->getList();
    $requisites = array();
    foreach($result as $item) {
        $requisite['code'] = $item['code'];
        $requisite['name'] = $item['title'];
        $requisite['size'] = (int) $item['size'];
        $requisites[] = $requisite;
    }

    $data['count'] = sizeof($requisites);
    $data['items'] = $requisites;

    if (mysql_error()) {
        $status['status'] = 'error';
        $status['error'] = mysql_error();
    } else {
        $status['status'] = 'ok';
        $status['data'] = $data;
    }

    outputData($status);