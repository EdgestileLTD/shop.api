<?php

function getNewName($dir, $name) {
    $i = 0;
    $newName = $name = convertName(trim($name));
    while (true) {
        if (!file_exists($dir . "/" . $newName))
            return $newName;
        $newName = substr($name, 0, strrpos($name, ".")) . "_" . ++$i . "." . end(explode(".", $name));
    }
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