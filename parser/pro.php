<?php

ini_set('display_errors', 1);
error_reporting(E_ERROR);

define('SE_INDEX_INCLUDED', true);
define('API_ROOT', "/home/e/edgestile/admin/home/siteedit/upload/api");
define('MAX_COUNT', 100);
define('DIR_IMAGES', '/home/e/edgestile/mebeldev.e-stile.ru/public_html/images/rus');

require_once 'import.class.php';
require_once 'parser.php';
require_once 'db.php';
require_once '/home/e/edgestile/admin/home/siteedit/lib/lib_function.php';
require_once '/home/e/edgestile/admin/home/siteedit/lib/lib_utf8.php';


$connection = array(
    "HostName" => "localhost",
    "DBName" => "edgestile_mebel",
    "DBUserName" => "edgestile_mebel",
    "DBPassword" => "mebeldev"
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


function getContent($url, $data = null, $headers = [], $isCached = true)
{
    $file_cache = API_ROOT . "/parser/cache/" . md5($url);

    if ($isCached && file_exists($file_cache))
        return file_get_contents($file_cache);

    $cookie = "cookies.txt";

    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_HEADER, 0);
    curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 30);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($curl, CURLOPT_FAILONERROR, 1);
    curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($curl, CURLOPT_COOKIEJAR, $cookie);
    curl_setopt($curl, CURLOPT_COOKIEFILE, $cookie);
    if ($data) {
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
    }
    if ($headers) {
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
    }
    curl_setopt($curl, CURLOPT_USERAGENT, 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_11_2) AppleWebKit/601.3.9');
    $result = curl_exec($curl);

    if (curl_errno($curl)) {
        echo "\nОшибка: " . curl_error($curl) . "\n";
        echo $result;
        if ($data)
            print_r($data);
        exit;
    }

    curl_close($curl);

    if ($isCached && (strlen($result) > 255)) {
        $file = fopen($file_cache, "w+");
        fputs($file, $result);
        fclose($file);
    };

    return $result;
}


function getImage($url, $dir)
{
    echo $url . "\n";
    $fileName = array_pop(explode("/", $url));
    $fileName = preg_replace("#\?.*#", "", $fileName);
    $dir = DIR_IMAGES . "/{$dir}/";
    mkdir($dir);
    $fileCache = "{$dir}" . $fileName;

    if (file_exists($fileCache))
        return $fileName;

    $result = getContent($url);

    if (strlen($result) > 255) {
        $file = fopen($fileCache, "w+");
        fputs($file, $result);
        fclose($file);
    } else $result = null;

    return $fileName;
}

function saveProduct($product)
{
    if (empty($product["img"]))
        return;

    $t = new DB("shop_price");
    $t->setValuesFields($product);
    $idProduct = $t->save();

    $t = new DB("shop_img");
    $t->setValuesFields(
        [
            "idPrice" => $idProduct,
            "picture" => $product["img"],
            "pictureAlt" => $product["imgAlt"],
            "title" => $product["name"],
            "default" => true
        ]
    );
    $t->save();
}

function updateProduct($product)
{
    $t = new DB("shop_price");
    $t->setValuesFields($product);
    $t->save();
}

echo "\n";
echo "Старт парсинга: " . date("H:i:s d.m.Y") . "\n";
$start = microtime(true);

//$file = getcwd() . "/import_mebel.xml";
//$reader = new XMLReader;
//$reader->open($file);
//
//while ($reader->read()) {
//    if ($reader->nodeType == XMLReader::ELEMENT) {
//        if ($reader->localName == 'Товар') {
//            $product = array();
//            $xml = new SimpleXMLElement($reader->readOuterXML());
//            $product["idExchange"] = (string)$xml->Ид;
//
//            $t = new DB("shop_price", "sp");
//            $t->select("sp.id");
//            $t->where("sp.id_exchange = '?'", $product["idExchange"]);
//            $result = $t->fetchOne();
//            if (!empty($result))
//                continue;
//
//            if ($xml->article)
//                $product['article'] = (string)$xml->article;
//            $product['name'] = (string)$xml->Наименование;
//            $product["title"] = $product["name"];
//            if ($xml->Описание)
//                $product['text'] = (string)$xml->Описание;
//            if ($xml->Картинка) {
//                $product['img'] = getImage('http://mebelvdom.ru' . (string)$xml->Картинка, 'shopprice');
//                $product['imgAlt'] = $product["name"];
//            }
//
//            saveProduct($product);
//        }
//    }
//}
//
//$reader->close();

$file = getcwd() . "/offers_mebel.xml";
$reader = new XMLReader;
$reader->open($file);

while ($reader->read()) {
    if ($reader->nodeType == XMLReader::ELEMENT) {
        if ($reader->localName == 'Предложение') {
            $product = array();
            $xml = new SimpleXMLElement($reader->readOuterXML());
            $product["idExchange"] = (string)$xml->Ид;

            $t = new DB("shop_price", "sp");
            $t->select("sp.id");
            $t->where("sp.id_exchange = '?'", $product["idExchange"]);
            $result = $t->fetchOne();
            if (empty($result))
                continue;

            $product["id"] = $result["id"];
            $product["price"] = (float)$xml->Цены->Цена->ЦенаЗаЕдиницу;
            updateProduct($product);
        }
    }
}

$reader->close();


echo 'Затраченное время: ' . (microtime(true) - $start) . ' сек.';
echo "\n";