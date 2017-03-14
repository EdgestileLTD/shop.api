<?php

if (IS_EXT) {
    require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/PHPExcel.php';
    require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/PHPExcel/Writer/Excel2007.php';
} else {
    require_once $_SERVER['DOCUMENT_ROOT'] . '/api/lib/PHPExcel/Classes/PHPExcel.php';
    require_once $_SERVER['DOCUMENT_ROOT'] . '/api/lib/PHPExcel/Classes/PHPExcel/Writer/Excel2007.php';
}

function getSeparator($file)
{
    $handle = fopen($file, "r");
    $buffer = null;
    while (!feof($handle))
        $buffer = fgets($handle, 8192);
    fclose($handle);

    if (empty($buffer))
        $buffer = file_get_contents($file);
    $countTab = substr_count($buffer, "\t");
    $countSemicolon = substr_count($buffer, ";");
    $countComma = substr_count($buffer, ",");
    $max = max([$countTab, $countSemicolon, $countComma]);
    if ($max == $countTab)
        return "\t";
    if ($max == $countSemicolon)
        return ";";
    return ",";
}

$step = isset($_GET['step']) ? $_GET['step'] : 1;
$ext = isset($_GET['ext']) ? $_GET['ext'] : "xlsx";
$keyField = isset($_GET['key']) ? $_GET['key'] : "id";
$encoding = isset($_GET['encoding']) ? $_GET['encoding'] : "UTF-8";
$skip = isset($_GET['skip']) ? $_GET['skip'] : 1;
$delimiter = isset($_GET['delimiter']) ? $_GET['delimiter'] : "Автоопределение";
$enclosure = isset($_GET['enclosure']) ? $_GET['enclosure'] : '"';

$root = API_ROOT;

IF (IS_EXT)
    $dir = '../app-data/imports';
else $dir = '../app-data/' . $json->hostname . '/imports';
if (!file_exists($root . $dir)) {
    $dirs = explode('/', $dir);
    $path = $root;
    foreach ($dirs as $d) {
        $path .= $d;
        if (!file_exists($path))
            mkdir($path, 0700);
        $path .= '/';
    }
}
$dir = $root . $dir;

$fields = ["id" => "Ид.", "article" => "Артикул", "code" => "Код (URL)", "id_group" => "Ид. группы",
    "code_group" => "Код группы", "name" => "Наименование", "price" => "Цена пр.", "price_opt" => "Цена опт.",
    "price_opt_corp" => "Цена корп.", "price_purchase" => "Цена закуп.", "presence_count" => "Остаток",
    "weight" => "Вес", "volume" => "Объем", "measure" => "Ед.Изм", "note" => "Краткое описание",
    "text" => "Полное описание", "curr" => "Код валюты", "title" => "Тег title", "keywords" => "Мета-тег keywords",
    "description" => "Мета-тег description", "img" => "Фото 1", "img_2" => "Фото 2",
    "img_3" => "Фото 3", "img_4" => "Фото 4", "img_5" => "Фото 5", "img_6" => "Фото 6",
    "img_7" => "Фото 7", "img_8" => "Фото 8",  "img_9" => "Фото 9", "img_10" => "Фото 10"];

$keyFields = ["Идентификатор" => "id", "Артикул" => "article", "Код (URL)" => "code", "Наименование" => "name"];

if ($step == 0) {

    $filePath = $dir . "/" . $_FILES["file"]["name"];

    if (!move_uploaded_file($_FILES["file"]['tmp_name'], $filePath)) {
        $status['status'] = 'error';
        $status['error'] = 'Не удаётся загрузить файл для импорта!';
        outputData($status);
        exit;
    }

    if ($ext == "xml") {
        if (IS_EXT)
            $projectDir = PATH_ROOT;
        else $projectDir = PATH_ROOT . $json->hostname . '/public_html';

        chdir($projectDir);
        $file_market = file_get_contents($fileName);
        if (empty($file_market))
            exit;
        $filePluginYML = $projectDir . '/lib/plugins/plugin_shop/plugin_yandex_market_loader.class.php';
        if (file_exists($filePluginYML)) {
            define('SE_DB_ENABLE', true);
            define('SE_SAFE', '');
            define('SE_DIR', '');
            define('SE_ROOT', $projectDir);
            include_once $projectDir . '/lib/lib_se_function.php';
            include_once $projectDir . '/lib/plugins/plugin_shop/plugin_shopgroups.class.php';
            include_once $filePluginYML;
            new yandex_market_loader($file_market);
            echo "ok";
        } else echo "Отсутствует плагин импорта YML!";

        exit;
    }

    if ($ext != "csv") {
        $typeDoc = $ext == "xls" ? 'Excel5' : 'Excel2007';
        $reader = PHPExcel_IOFactory::createReader($typeDoc);
        $reader->setReadDataOnly(true);
        $excel = $reader->load($filePath);
        $fileCSV = "{$dir}/groups.csv";
        $writer = \PHPExcel_IOFactory::createWriter($excel, 'CSV');
        $writer->save($fileCSV);
    } else $fileCSV = $filePath;


    $fields = array_values($fields);

    if ($delimiter == "Автоопределение") {
        $separator = getSeparator($fileCSV);
    } elseif ($delimiter == "Точка с запятой") {
        $separator = ";";
    } elseif ($delimiter == "Запятая") {
        $separator = ",";
    } else $separator = "\t";

    $count = 0;
    $maxHeaderRows = 25;
    $samples = [];

    if (($handle = fopen($fileCSV, "r")) !== false) {
        $i = 0;
        while (($row = fgetcsv($handle, 16000, $separator)) !== false &&
            $i++ < ($maxHeaderRows + $skip)) {
            if ($i < $skip + 1)
                continue;
            if (count($row) > $count) {
                $count = count($row);
                $j = 0;
                foreach ($row as $key => $value) {
                    if ($encoding != "UTF-8")
                        $value = iconv('CP1251', 'UTF-8', $value);
                    $samples[$j++] = $value;
                }
            }
        }
    }
    fclose($handle);

    $count = count($samples);
    $cols = [];
    for ($i = 0; $i < $count; $i++)
        $cols[] = ["id" => $i, "title" => "Столбец № {$i}", "sample" => $samples[$i]];

    $_SESSION["import"]["product"]["delimiter"] = $delimiter;
    $_SESSION["import"]["product"]["separator"] = $separator;
    $_SESSION["import"]["product"]["encoding"] = $encoding;
    $_SESSION["import"]["product"]["key"] = $keyField;
    $_SESSION["import"]["product"]["enclosure"] = $enclosure;
    $_SESSION["import"]["product"]["skip"] = $skip;

    $t = new seTable("shop_feature", "sf");
    $t->select("sf.name");
    $t->orderBy("sf.name");
    $features = $t->getList();
    foreach ($features as $feature)
        $fields[] = $feature["name"];

    $status['status'] = 'ok';
    $status['data'] = ["cols" => $cols, "fields" => $fields];

    outputData($status);
}


if ($step == 1) {

    $separator = $_SESSION["import"]["product"]["separator"];
    $encoding = $_SESSION["import"]["product"]["encoding"];
    $keyUser = $_SESSION["import"]["product"]["key"];
    $enclosure = $_SESSION["import"]["product"]["enclosure"];
    $skip = $_SESSION["import"]["product"]["skip"];
    $keyField = $keyFields[$keyUser];

    $_SESSION["import"]["product"]["cols"] = $cols = $json->listValues;
    $fileCSV = "{$dir}/catalog.csv";
    $countInsert = 0;
    $countUpdate = 0;
    $groups = [];


    if (($handle = fopen($fileCSV, "r")) !== false) {
        $i = 0;

        se_db_query("SET AUTOCOMMIT=0; START TRANSACTION");
        while (($row = fgetcsv($handle, 16000, $separator)) !== false) {
            if ($i++ < $skip)
                continue;



        }
    }
    fclose($handle);

    se_db_query("COMMIT");



    $status['status'] = 'ok';
    $status['data'] = ["countInsert" => (int) $countInsert, "countUpdate" => (int) $countUpdate];
    outputData($status);

}
