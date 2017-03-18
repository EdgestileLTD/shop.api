<?php

if (IS_EXT) {
    require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/PHPExcel.php';
    require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/PHPExcel/Writer/Excel2007.php';
} else
    require_once($_SERVER['DOCUMENT_ROOT'] . '/api/lib/ExcelWriter/autoload.php');


$outFile = "catalog.xlsx";
header("Expires: Mon, 1 Apr 1974 05:00:00 GMT");
header("Last-Modified: " . gmdate("D,d M YH:i:s") . " GMT");
header("Cache-Control: no-cache, must-revalidate");
header("Pragma: no-cache");
header("Content-type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename={$outFile}");

// преобразование переменных запроса в переменные БД
function convertFields($str)
{
    $str = str_replace('idGroup ', 'sg.id ', $str);
    $str = str_replace('[id]', 'sp.id ', $str);
    $str = str_replace('[idGroup]', 'sg.id ', $str);
    $str = str_replace('[idCrossGroup]', 'sgp.group_id ', $str);
    $str = str_replace('[nameGroup]', 'namegroup ', $str);
    $str = str_replace('[count]', 'presence_count', $str);
    $str = str_replace('[isNew]=true', 'sp.flag_new="Y"', $str);
    $str = str_replace('[isNew]=false', 'sp.flag_new="N"', $str);
    $str = str_replace('[isHit]=true', 'sp.flag_hit="Y"', $str);
    $str = str_replace('[isHit]=false', 'sp.flag_hit="N"', $str);
    $str = str_replace('[isActive]=true', 'sp.enabled="Y"', $str);
    $str = str_replace('[isActive]=false', 'sp.enabled="N"', $str);
    $str = str_replace('[isDiscount]=true', 'sd.discount_value>0 AND sp.discount="Y"', $str);
    $str = str_replace('[isDiscount]=false', '(sd.discount_value IS NULL OR sd.discount_value=0 OR sp.discount="N")', $str);
    $str = str_replace('[isInfinitely]=true', '(sp.presence_count IS NULL OR sp.presence_count<0)', $str);

    return $str;
}

$filter = convertFields($json->filter);

function getGroup($groups, $idGroup)
{
    if (!$idGroup)
        return null;

    foreach ($groups as $group) {
        if ($group["id"] == $idGroup) {
            if ($group['upid'])
                return getGroup($groups, $group['upid']) . "/" . $group["name"];
            else return $group["name"];
        }
    }
}

function getGroup53($groups, $idGroup)
{
    if (!$idGroup)
        return null;

    foreach ($groups as $group) {
        if ($group["id"] == $idGroup)
            return $group["name"];
    }
}

$fields = ["id" => "Ид.", "article" => "Артикул", "code" => "Код (URL)", "id_group" => "Ид. категории",
    "code_group" => "Код категории",  "path_group" => "Путь категории", "name" => "Наименование",
    "price" => "Цена пр.", "price_opt" => "Цена опт.",
    "price_opt_corp" => "Цена корп.", "price_purchase" => "Цена закуп.", "presence_count" => "Остаток",
    "brand" => "Бренд", "weight" => "Вес", "volume" => "Объем", "measure" => "Ед.Изм", "note" => "Краткое описание",
    "text" => "Полное описание", "curr" => "Код валюты", "title" => "Тег title", "keywords" => "Мета-тег keywords",
    "description" => "Мета-тег description", "img" => "Фото 1", "img_2" => "Фото 2",
    "img_3" => "Фото 3", "img_4" => "Фото 4", "img_5" => "Фото 5", "img_6" => "Фото 6",
    "img_7" => "Фото 7", "img_8" => "Фото 8", "img_9" => "Фото 9", "img_10" => "Фото 10"];

$t = new seTable("shop_feature", "sf");
$t->select("sf.name");
$t->orderBy("sf.id");
$features = $t->getList();
foreach ($features as $feature)
    $fields[$feature["name"]] = $feature["name"];

$u = new seTable('shop_price', 'sp');
$select = 'sp.*, GROUP_CONCAT(si.picture SEPARATOR ";") photos, sb.name brand,              
                (SELECT GROUP_CONCAT(CONCAT_WS("#", sf.name,
                    IF(smf.id_value IS NOT NULL, sfvl.value, CONCAT(IFNULL(smf.value_number, ""), 
                    IFNULL(smf.value_bool, ""), IFNULL(smf.value_string, "")))) SEPARATOR ";") features
                    FROM shop_modifications_feature smf
                    INNER JOIN shop_feature sf ON smf.id_feature = sf.id AND smf.id_modification IS NULL
                    LEFT JOIN shop_feature_value_list sfvl ON smf.id_value = sfvl.id
                    WHERE smf.id_price = sp.id
                    GROUP BY smf.id_price) features';
if (CORE_VERSION == "5.3") {
    $select .= ', spg.id_group id_group_t';
    $u->select($select);
    $u->leftJoin("shop_price_group spg", "spg.id_price = sp.id AND spg.is_main");
} else $u->select($select);
$u->leftJoin('shop_modifications sm', 'sm.id_price = sp.id');
$u->leftJoin('shop_img si', 'si.id_price = sp.id');
$u->leftJoin('shop_brand sb', 'sp.id_brand = sb.id');
$u->orderBy('sp.id');
$u->groupBy('sp.id');

$products = $u->getList();

$u = new seTable('shop_group', 'sg');
if (CORE_VERSION == "5.3") {
    $u->select('sg.id, sg.code_gr, GROUP_CONCAT(sgp.name ORDER BY sgt.level SEPARATOR "/") name');
    $u->innerJoin("shop_group_tree sgt", "sg.id = sgt.id_child");
    $u->innerJoin("shop_group sgp", "sgp.id = sgt.id_parent");
    $u->orderBy('sgt.level');
} else {
    $u->select('sg.*');
    $u->orderBy('sg.id');
}
$u->groupBy('sg.id');
$items = $u->getList();
$groups = [];
foreach ($items as $item)
    $groups[$item["id"]] = $item;

$cols = [];
$symbols = [];
for ($i = 65; $i <= 90; $i++)
    $cols[] = $symbols[] = chr($i);
for ($i = 0; $i < 10; $i++)
    foreach ($symbols as $sym)
        $cols[] = $cols[$i] . $sym;
$colsFill = [];

if (IS_EXT) {
    $xls = new PHPExcel();
    $xls->setActiveSheetIndex(0);
    $sheet = $xls->getActiveSheet();
    $sheet->setTitle('Товары');
} else {
    $wExcel = new Ellumilel\ExcelWriter();
    $wExcel->setAuthor('SiteEdit Manager 5.3');
}

$i = 0;
$row = 1;
$header = [];
foreach ($fields as $key => $field)
    if (IS_EXT)
        $sheet->setCellValue("{$cols[$i++]}{$row}", $field);
    else $header[$field] = "string";

$row++;
foreach ($products as $product) {
    $i = 0;
    if (CORE_VERSION == "5.3")
        $product["id_group"] = $product["id_group_t"];

    if (CORE_VERSION == "5.3")
        $product["path_group"] = getGroup53($groups, $product["id_group"]);
    else $product["path_group"] = getGroup($groups, $product["id_group"]);
    $product["code_group"] = $groups[$product["id_group"]]["code_gr"];

    $photos = explode(";", $product["photos"]);
    for ($p = 0; $p < count($photos); $p++) {
        if ($p) {
            $pk = $p + 1;
            $product["img_{$pk}"] = $photos[$p];
        }
        else $product["img"] = $photos[0];
    }
    $features = [];
    if ($product["features"]) {
        $featuresV = explode(";", $product["features"]);
        foreach ($featuresV as $featureV) {
            $values = explode("#", $featureV);
            if (key_exists($values[0], $features))
                $features[$values[0]] = $features[$values[0]] . ", " . $values[1];
            else $features[$values[0]] = $values[1];
        }
        foreach ($features as $key => $value)
            $product[$key] = $value;
    }
    $dataItem = [];
    foreach ($fields as $key => $field) {
        if (!empty($product[$key]) && !in_array($i, $colsFill))
            $colsFill[] = $i;
        if (IS_EXT)
            $sheet->setCellValue("{$cols[$i++]}{$row}", $product[$key]);
        else $dataItem[] = $product[$key];
    }
    $data[] = $dataItem;
    $row++;
}

if (IS_EXT) {
    $i = 0;
    foreach ($fields as $field) {
        if (in_array($i, $colsFill))
            $sheet->getColumnDimension("{$cols[$i]}")->setAutoSize(true);
        $i++;
    }
    $objWriter = new PHPExcel_Writer_Excel2007($xls);
    $objWriter->save('php://output');
} else {
    $wExcel->writeSheet($data, 'Товары', $header);
    $wExcel->writeToStdOut();
}

