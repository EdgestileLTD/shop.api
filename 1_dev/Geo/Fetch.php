<?php

$idCountry = $json->idCountry ? $json->idCountry : $json->idMain;
$idRegion = $json->idRegion ? $json->idRegion : $json->idGroup;
$idCity = $json->idCity ? $json->idCity : $_GET['idCity'];
$search = ($json->searchText) ? $json->searchText : $_GET['search'];

if (!$idCountry && !$idRegion && !$idCity && !$search) {
    $this->result["items"] = array();
    $this->result["count"] = 0;
    return;
}
$data = array('action' => 'geo',
    'idCountry' => $idCountry,
    'idRegion' => $idRegion,
    'idCity' => $idCity,
    'search' => $search);
$data = http_build_query($data);
$url = "https://api.siteedit.ru/api/geo/?".$data;
$curl = curl_init($url);
curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
$data = json_decode(curl_exec($curl), true);
$status['status'] = 'ok';
$status['data'] = $data;
outputData($status);