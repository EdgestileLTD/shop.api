<?php

function convertName($name) {
    $chars = array(" ", "#", ":", "!", "+", "?", "&", "@", "~", "%");
    return str_replace($chars, "_", $name);
}

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
$lang = $_SESSION['language'] ? $_SESSION['language'] : 'rus';

$listFiles = array();
$dir = $dirRoot . "/images/$lang/$section";

$names = $json->listValues;
foreach ($names as $name)
    $newNames[] = getNewName($dir, $name);

$item['newNames'] = $newNames;

$data['count'] = 1;
$data['items'][0] = $item;

$status['status'] = 'ok';
$status['data'] = $data;

outputData($status);