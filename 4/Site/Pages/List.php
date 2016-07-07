<?php

$path = PATH_ROOT . $json->hostname . '/public_html/';
$ver = $path . 'lib/version';
if (file_exists($ver)){
    $ver = join('', file($ver));
}

$status['status'] = 'ok';
$status['data'] =  $ver;
outputData($status);