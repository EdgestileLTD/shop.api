<?php

    $mysqli = new mysqli('edgestile.com', 'edgemain_public', '9gRxbs7n', 'edgemain_public');

    if ($mysqli->connect_error) {
        $status['status'] = 'error';
        $status['errortext'] = 'Не удаётся получить список регионов!';
        outputData($status);
        exit;
    }


    $search = ($json->searchText) ? $json->searchText : $_GET['search'];
    $ids = array();
    if (empty($json->ids) && !empty($json->id))
        $ids[] = $json->id;
    else $ids = $json->ids;
    $idsStr = implode(",", $ids);
    if (empty($idsStr))
        $idsStr = $_GET['id'];

    $sqlQuery = "SELECT * FROM net_regions";
    $sqlWhere = array();
    if (!empty($idsStr)) {
        $sqlQuery .= " WHERE id IN (" . $idsStr . ")";
    } else {
        if (!empty($search))
            $sqlWhere[] = "(LOWER(name_ru) LIKE '" . strtolower($search) . "%')";
        if ($idsStr)
            $sqlWhere[] = "(id IN ({$idsStr}))";
        $sqlWhere = implode(" AND ", $sqlWhere);
        if ($sqlWhere)
            $sqlQuery .= " WHERE {$sqlWhere}";
    }
    $sqlQuery .= " ORDER BY name_ru";

    $regionsList = array();
    if ($result = $mysqli->query($sqlQuery)) {
        while ($row = $result->fetch_assoc()) {
            $region['id'] = $row['id'];
            $region['idMain'] = $row['id_country'];
            $region['code'] = $row['UTC'];
            $region['name'] = $row['name_ru'];
            $regionsList[] = $region;
        }
        $result->close();
    }
    $mysqli->close();

    $count = sizeof($regionsList);

    $data['count'] = $count;
    $data['items'] = $regionsList;

    $status = array();
    if (!$mysqli->error) {
        $status['status'] = 'ok';
        $status['data'] = $data;
    } else {
        $status['status'] = 'error';
        $status['errortext'] = 'Не удаётся получить список регионов!';
    }

    outputData($status);