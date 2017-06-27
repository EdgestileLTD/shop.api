<?php


ini_set('display_errors', 1);
error_reporting(E_ERROR);

define('SE_INDEX_INCLUDED', true);
define('API_ROOT', getcwd() . "/");
define('DIR_DATA', 'data');
define('PATH_DATA', API_ROOT . DIR_DATA . "/");
define('MAX_COUNT', 100);

require_once 'import.class.php';
require_once 'parser.php';
require_once 'db.php';
require_once API_ROOT . '../lib/lib_function.php';
require_once API_ROOT . '../lib/lib_utf8.php';


$connection = array(
    "HostName" => "localhost",
    "DBName" => "edgestile_124380",
    "DBUserName" => "edgestile_124380",
    "DBPassword" => "ce64d52938"
);

DB::initConnection($connection);

function writeLog($data)
{
    if (!is_string($data))
        $data = print_r($data, 1);

    $file = fopen(API_ROOT . "debug.log", "a+");
    $query = "$data" . "\n";
    fputs($file, $query);
    fclose($file);
}

$pathImages = "../images/rus/shopprice/";

$t = new DB("shop_price", "sp");
$t->select("sp.id, sp.img");
$list = $t->getList();
foreach ($list as $value) {
    $fileImage = $pathImages . $value["img"];
    if (!file_exists($fileImage)) {
        $ids[] = $value["id"];
        continue;
    }


    if (filesize($fileImage) < 512) {
        unlink($fileImage);
        $ids[] = $value["id"];
        echo $fileImage . "\n";
        echo "Id product: {$value['id']}\n";
    }
}

if (!empty($ids)) {
    echo "Delete " . count($ids) . " products!\n";
    $ids = implode(",", $ids);
    DB::query("UPDATE shop_price SET img = NULL WHERE id IN ({$ids})");
    DB::query("DELETE FROM shop_img WHERE id_price IN ({$ids})");
    DB::query("UPDATE shop_price SET enabled = 'N' WHERE img IS NULL");
}
echo "finish\n";

