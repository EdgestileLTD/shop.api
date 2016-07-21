<?php

$idCountry = $json->idCountry ? $json->idCountry : $json->idMain;
$idRegion = $json->idRegion ? $json->idRegion : $json->idGroup;
$search = ($json->searchText) ? $json->searchText : $_GET['search'];
$ids = array();
if (empty($json->ids) && !empty($json->id))
    $ids[] = $json->id;
else $ids = $json->ids;
if (!$idCountry && !$idRegion && !$search && !$ids) {
    $this->result["items"] = array();
    $this->result["count"] = 0;
    return;
}
$data = array('action' => 'city',
    'idCountry' => $idCountry,
    'idRegion' => $idRegion,
    'search' => $search,
    'ids' => $ids);
$data = http_build_query($data);
$url = "https://api.siteedit.ru/api/geo/?".$data;
$curl = curl_init($url);
curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
$data = json_decode(curl_exec($curl), true);
$status['status'] = 'ok';
$status['data'] = $data;
outputData($status);