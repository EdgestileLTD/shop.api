<?php

    if ($json->ids) {
        $ids = implode(",", $json->ids);
        $u = new seTable('news','n');
        $u->where('id in (?)', $ids)->deletelist();
    }

    $status = array();
    if (!mysql_error()) {
        $status['status'] = 'ok';
    } else {
        $status['status'] = 'error';
        $status['errortext'] = mysql_error();
    }

    outputData($status);