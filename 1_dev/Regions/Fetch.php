<?php

$ids = array();
if (empty($json->ids) && !empty($json->ids))
    $ids[] = $json->id;
else $ids = $json->ids;
$data = array('action' => 'region',
    'idCountry' => $json->idCountry,
    'search' => $json->searchText,
    'ids' => $ids);
$data = http_build_query($data);
$url = "https://api.siteedit.ru/api/geo/?".$data;
$curl = curl_init($url);
curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
$data = json_decode(curl_exec($curl), true);
$status['status'] = 'ok';
$status['data'] = $data;
outputData($status);