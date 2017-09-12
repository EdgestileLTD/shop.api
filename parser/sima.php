<?php

ini_set('display_errors', 1);
error_reporting(E_ERROR | E_PARSE);

define('SE_INDEX_INCLUDED', true);
define('API_ROOT', "/home/e/edgestile/admin/home/siteedit/upload/api");
define('MAX_COUNT', 100);
define('DIR_IMAGES', '/home/e/edgestile/mebeldev.e-stile.ru/public_html/images/rus');

define('LOGIN', 'hmm19792@gmail.com');
define('PASSWORD', 'qaedblo5');

$pathData = API_ROOT . DIRECTORY_SEPARATOR . "temp" . DIRECTORY_SEPARATOR;

require_once 'import.class.php';
require_once 'parser.php';
require_once 'db.php';

include_once  'vendor/GuzzleHttp/functions_include.php';
include_once  'vendor/GuzzleHttp/Psr7/functions_include.php';
include_once  'vendor/GuzzleHttp/Promise/functions_include.php';

require_once '/home/e/edgestile/admin/home/siteedit/lib/lib_function.php';
require_once '/home/e/edgestile/admin/home/siteedit/lib/lib_utf8.php';

require_once "vendor/autoload.php";

$connection = array(
    "HostName" => "localhost",
    "DBName" => "edgestile_146512",
    "DBUserName" => "edgestile_146512",
    "DBPassword" => "d68a53aca7"
);

DB::initConnection($connection);

function writeLog($data)
{
    if (!is_string($data))
        $data = print_r($data, 1);

    $file = fopen(API_ROOT . "/parser/debug.log", "a+");
    $query = "$data" . "\n";
    fputs($file, $query);
    fclose($file);
}

$client = new \SimaLand\API\Rest\Client([
    'login' => LOGIN,
    'password' => PASSWORD
]);
$response = $client->get('category', ['level' => 1]);

$body = json_decode($response->getBody(), true);
print_r($body);
