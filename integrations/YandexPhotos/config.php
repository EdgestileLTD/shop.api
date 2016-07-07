<?php

define('CLIENT_ID', 'dd26c479268e4aedbf08db74f7f2addb');
define('CLIENT_SECRET', '182c27375cac40d2adda30d37c830332');

function writeLog($data)
{
    $file = fopen($_SERVER['DOCUMENT_ROOT'] . "/api/debug.txt", "a+");
    $query = "$data" . "\n";
    fputs($file, $query);
    fclose($file);
}