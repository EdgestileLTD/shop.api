<?php
    $vars = $json->vars;

    $i = 0;
    $items = array();
    foreach($vars as $var) {
        $items[] = se_translite_url($var);
        $i++;
    }

    $data['count'] = $i;
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