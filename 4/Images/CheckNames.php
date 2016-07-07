<?php

function getNewName($dir, $name) {

}

if (IS_EXT)
    $dirRoot = $_SERVER['DOCUMENT_ROOT'];
else $dirRoot = '/home/e/edgestile/' . $json->hostname . '/public_html';
$section = $json->section;
$lang = 'rus';

$listFiles = array();
$dir = $dirRoot . "/images/$lang/$section";

$names = $json->names;
foreach ($names as $name) {
    $item = [];
    $item['oldName'] = $name;
    $item['newName'] = getNewName($dir, $name);
    $items[] = $item;
}

$data['count'] = $count;
$data['items'] = $items;

$status['status'] = 'ok';
$status['data'] = $data;

outputData($status);