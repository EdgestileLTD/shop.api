<?php

    $idCountry = $json->idCountry ? $json->idCountry : $_GET['idCountry'];
    if (empty($idCountry))
        $idCountry = $json->idMain;
    $search = ($json->searchText) ? $json->searchText : $_GET['search'];
    $ids = array();
    if (empty($json->ids) && !empty($json->id))
        $ids[] = $json->id;
    else $ids = $json->ids;
    $idsStr = implode(",", $ids);
    if (empty($idsStr))
        $idsStr = $_GET['id'];

    $db = mysql_connect('edgestile.com','edgemain_public','9gRxbs7n');
    mysql_query("SET character_set_client='UTF8'", $db);
    mysql_query("SET character_set_results='UTF8'", $db);
    mysql_query("set collation_connection='utf8_general_ci'", $db);
    $result = mysql_select_db('edgemain_public', $db);
    $regionList = array();
    if ($result) {
        $sql = "SELECT * FROM geo_cities";
        if (!empty($idsStr)) {
            $sql .= " WHERE id IN (" . $idsStr . ")";
        } else {
            if (!empty($search))
                $sql .= " WHERE LOWER(city) LIKE '" . strtolower($search) . "%'";
        }
        $result = mysql_query($sql, $db);

        while ($row = mysql_fetch_assoc($result)) {
            $regionList[] = array('id' => $row['id'], 'name' => $row['city'],
                'title' => $row['city']);
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