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


//

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
//            if (!empty($result)) {
//
//                $idProduct = $result["id"];
//
//                foreach ($xml->ЗначенияСвойств as $xmlProperties) {
//                    foreach ($xmlProperties as $xmlProperty) {
//
//                        $property = array();
//                        $property["id"] = (string)$xmlProperty->Ид;
//                        $property["value"] = (string)$xmlProperty->Значение;
//
//                        print_r($property);
//
//                        if (empty($property["value"]))
//                            continue;
//
//                        $u = new DB("shop_feature", "sf");
//                        $u->select("sf.id, sf.type");
//                        $u->where("sf.id_exchange = '?'", $property["id"]);
//                        $result = $u->fetchOne();
//                        if (!empty($result)) {
//                            echo "ok\n";
//                            $idFeature = $result["id"];
//                            if ($result["type"] == "list" || $result["type"] == "colorlist") {
//                                $value = (string)$property["value"];
//                                $u = new DB("shop_feature_value_list", "sfl");
//                                $u->select("sfl.id");
//                                $u->where("sfl.id_feature = ?", $idFeature);
//                                $u->andWhere("sfl.value = '?'", $value);
//                                $answer = $u->fetchOne();
//                                if (empty($answer)) {
//                                    $u = new DB("shop_feature_value_list");
//                                    $data = array();
//                                    $data["idFeature"] = $idFeature;
//                                    $data["value"] = $value;
//                                    $u->setValuesFields($data);
//                                    $idValue = $u->save();
//                                } else $idValue = $answer["id"];
//                                $u = new DB("shop_modifications_feature", "smf");
//                                $u->where("smf.id_price = ?", $idProduct);
//                                $u->andWhere("smf.id_value = '?'", $idValue);
//                                $answer = $u->fetchOne();
//                                if (empty($answer)) {
//                                    $u = new DB("shop_modifications_feature");
//                                    $data = array();
//                                    $data["idPrice"] = $idProduct;
//                                    $data["idFeature"] = $idFeature;
//                                    $data["idValue"] = $idValue;
//                                    $u->setValuesFields($data);
//                                    $u->save();
//                                }
//                            }
//                            if ($result["type"] == "number") {
//                                $u = new DB("shop_modifications_feature", "smf");
//                                $u->where("smf.id_price = ?", $idProduct);
//                                $u->andWhere("smf.value_number = '?'", (float)$property["value"]);
//                                $answer = $u->fetchOne();
//                                if (empty($answer)) {
//                                    $u = new DB("shop_modifications_feature");
//                                    $data = array();
//                                    $data["idPrice"] = $idProduct;
//                                    $data["idFeature"] = $idFeature;
//                                    $data["valueNumber"] = (float)$property["value"];
//                                    $u->setValuesFields($data);
//                                    $u->save();
//                                }
//
//                            }
//                            if ($result["type"] == "bool") {
//                                $u = new DB("shop_modifications_feature", "smf");
//                                $u->where("smf.id_price = ?", $idProduct);
//                                $u->andWhere("smf.value_bool = '?'", (bool)$property["value"]);
//                                $answer = $u->fetchOne();
//                                if (empty($answer)) {
//                                    $u = new DB("shop_modifications_feature");
//                                    $data = array();
//                                    $data["idPrice"] = $idProduct;
//                                    $data["idFeature"] = $idFeature;
//                                    $data["valueBool"] = (bool)$property["value"];
//                                    $u->setValuesFields($data);
//                                    $u->save();
//                                }
//                            }
//                        }
//
//
//                    }
//                }
//
//
//                continue;
//            }
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
//
//exit;
//
//$file = getcwd() . "/offers_mebel.xml";
//$reader = new XMLReader;
//$reader->open($file);
//
//while ($reader->read()) {
//    if ($reader->nodeType == XMLReader::ELEMENT) {
//        if ($reader->localName == 'Предложение') {
//            $product = array();
//            $xml = new SimpleXMLElement($reader->readOuterXML());
//            $product["idExchange"] = (string)$xml->Ид;
//
//            $t = new DB("shop_price", "sp");
//            $t->select("sp.id");
//            $t->where("sp.id_exchange = '?'", $product["idExchange"]);
//            $result = $t->fetchOne();
//            if (empty($result))
//                continue;
//
//            $product["id"] = $result["id"];
//            $product["price"] = (float)$xml->Цены->Цена->ЦенаЗаЕдиницу;
//            updateProduct($product);
//        }
//    }
//}
//
//$reader->close();

$urlRoot = 'https://mebelvdom.ru';
$html = getContent($urlRoot);
$html = str_replace('<meta charset="UTF-8" />', '<meta http-equiv="Content-Type" content="text/html; charset=UTF-8"/>', $html);
$saw = new parserHtml($html);
$res = $saw->get('a.one_directory_partitions')->toArray();
foreach ($res as $resGroup) {

    $urlGroup = $resGroup["href"];
    while (!empty($urlGroup)) {

        $urlGroup = $urlRoot . $urlGroup;
        echo $urlGroup . "\n";
        $html = getContent($urlGroup);
        $html = str_replace('<meta charset="UTF-8" />', '<meta http-equiv="Content-Type" content="text/html; charset=UTF-8"/>', $html);
        $saw = new parserHtml($html);
        $res = $saw->get('.show-more-button')->toArray();
        $urlGroup = $res["data-url"];

        $res = $saw->get('.on_title a')->toArray();
        foreach ($res as $cardItem) {
            $url = $urlRoot . $cardItem["href"];

            $html = getContent($url);
            $html = str_replace('<meta charset="UTF-8" />', '<meta http-equiv="Content-Type" content="text/html; charset=UTF-8"/>', $html);
            $saw = new parserHtml($html);
            $res = $saw->get('h1')->toArray();
            $name = $res["#text"];
            $t = new DB("shop_price", "sp");
            $t->select("sp.id, sp.text");
            $t->where("name = '?'", $name);
            $result = $t->fetchOne();
            if (empty($result))
                continue;

            $idProduct = $result["id"];

            if (empty($result["text"])) {
                $matches = array();
                $html = str_replace("\r", "", $html);
                $html = str_replace("\n", " ", $html);
                preg_match_all('#<div class="text-normal post-content">(.*<p class="space-bottom">.*)</div>#U', $html, $matches);
                $text = trim($matches[1][0]);
                $data = array();
                $data["id"] = $idProduct;
                $data["text"] = $text;
                $t = new DB("shop_price");
                $t->setValuesFields($data);
                $t->save();
            }


            $res = $saw->get('.accessory-item label')->toArray();
            foreach ($res as $resAccessory) {
                $idOptionValue = $resAccessory["input"][0]["value"];

                if (empty($idOptionValue)) {
                    writeLog("Не найдено:" . $name . "\n");
                    continue;
                }

                $t = new DB("shop_product_option", "spo");
                $t->select("spo.id");
                $t->where("spo.id_product = ?", $idProduct);
                $t->andWhere("spo.id_option_value = ?", $idOptionValue);
                $result = $t->fetchOne();
                if (!empty($result))
                    continue;

                $data = array();
                $t = new DB("shop_product_option");
                $data["idProduct"] = $idProduct;
                $data["idOptionValue"] = $idOptionValue;
                $t->setValuesFields($data);
                $t->save();

                echo "Аксессуары\n";
                print_r($data);
            }

            $res = $saw->get('.texture-item label')->toArray();
            foreach ($res as $resAccessory) {
                $idTextures = $resAccessory["input"][0]["value"];

                if (empty($idTextures)) {
                    writeLog("Не найдено:" . $name . "\n");
                    continue;
                }

                $t = new DB("shop_option_value", "sov");
                $t->select("sov.id");
                $t->where("sov.id_textures = ?", $idTextures);
                $result = $t->fetchOne();
                if (empty($result))
                    continue;

                $idOptionValue = $result["id"];

                $t = new DB("shop_product_option", "spo");
                $t->select("spo.id");
                $t->where("spo.id_product = ?", $idProduct);
                $t->andWhere("spo.id_option_value = ?", $idOptionValue);
                $result = $t->fetchOne();
                if (!empty($result))
                    continue;

                $data = array();
                $t = new DB("shop_product_option");
                $data["idProduct"] = $idProduct;
                $data["idOptionValue"] = $idOptionValue;
                $t->setValuesFields($data);
                $t->save();

                echo "Цвета\n";
                print_r($data);
            }
        }
    }
}

echo 'Затраченное время: ' . (microtime(true) - $start) . ' сек.';
echo "\n";