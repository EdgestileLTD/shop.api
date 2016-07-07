<?php
    $urlRoot = 'http://' .$json->hostname;

    $buffer = file_get_contents($urlRoot . "/lib/merchant/getlist.php");
    $items = explode("|", $buffer);
    $plugins = array();
    foreach($items as $item)
        if (!empty($item)) {
            $plugin['id'] = $item;
            $plugin['name'] = $item;
            $plugins[] = $plugin;
        }

    $data['count'] = sizeof($plugins);
    $data['items'] = $plugins;

    $status = array();
    if (!mysql_error()) {
        $status['status'] = 'ok';
        $status['data'] = $data;
    } else {
        $status['status'] = 'error';
        $status['errortext'] = mysql_error();
    }
    outputData($status);
