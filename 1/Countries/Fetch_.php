<?php

    $db = mysql_connect('edgestile.com','edgemain_public','9gRxbs7n');
    mysql_query("SET character_set_client='UTF8'", $db);
    mysql_query("SET character_set_results='UTF8'", $db);
    mysql_query("set collation_connection='utf8_general_ci'", $db);
    $result = mysql_select_db('edgemain_public', $db);
    $regionList = array();
    if ($result) {
        $result = mysql_query("SELECT id, name_ru FROM net_country ORDER BY name_ru", $db);

        while ($row = mysql_fetch_assoc($result)) {
            $regionList[] = array('id' => $row['id'], 'name' => $row['name_ru'], 'title' => $row['name_ru']);
        }
    }

    $count = sizeof($regionList);

    $data['count'] = $count;
    $data['items'] = $regionList;

    $status = array();
    if (!mysql_error()) {
        $status['status'] = 'ok';
        $status['data'] = $data;
    } else {
        $status['status'] = 'error';
        $status['errortext'] = mysql_error();
    }
    outputData($status);