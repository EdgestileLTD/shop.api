<?php

    $mysqli = new mysqli('edgestile.com', 'edgemain_public', '9gRxbs7n', 'edgemain_public');

    if ($mysqli->connect_error) {
        $status['status'] = 'error';
        $status['errortext'] = 'Не удаётся получить список городов!';
        outputData($status);
        exit;
    }

    $idCountry = $json->idCountry ? $json->idCountry : $_GET['idCountry'];
    $idRegion = $json->idRegion ? $json->idRegion : $_GET['idRegion'];
    if (empty($idCountry))
        $idCountry = $json->idMain;
    if (empty($idRegion))
        $idRegion = $json->idGroup;
    $search = ($json->searchText) ? $json->searchText : $_GET['search'];
    $ids = array();
    if (empty($json->ids) && !empty($json->id))
        $ids[] = $json->id;
    else $ids = $json->ids;
    $idsStr = implode(",", $ids);
    if (empty($idsStr))
        $idsStr = $_GET['id'];

    $sqlQuery = "SELECT * FROM net_city";
    $sqlWhere = array();
    if (!empty($idsStr)) {
        $sqlQuery .= " WHERE id IN (" . $idsStr . ")";
    } else {
        if (!empty($search))
            $sqlWhere[] = "(LOWER(name_ru) LIKE '" . strtolower($search) . "%')";
        if ($idCountry)
            $sqlWhere[] = "(country_id = {$idCountry})";
        if ($idRegion)
            $sqlWhere[] = "(region_id = {$idRegion})";
        $sqlWhere = implode(" AND ", $sqlWhere);
        if ($sqlWhere)
            $sqlQuery .= " WHERE {$sqlWhere}";
    }
    $sqlQuery .= " ORDER BY name_ru";

    $citiesList = array();
    if ($result = $mysqli->query($sqlQuery)) {
        while ($row = $result->fetch_assoc()) {
            $city['id'] = $row['id'];
            $city['idMain'] = $row['country_id'];
            $city['idGroup'] = $row['region_id'];
            $city['name'] = $row['name_ru'];
            $citiesList[] = $city;
        }
        $result->close();
    }
    $mysqli->close();

    $count = sizeof($citiesList);

    $data['count'] = $count;
    $data['items'] = $citiesList;

    $status = array();
    if (!$mysqli->error) {
        $status['status'] = 'ok';
        $status['data'] = $data;
    } else {
        $status['status'] = 'error';
        $status['errortext'] = 'Не удаётся получить список городов!';
    }

    outputData($status);