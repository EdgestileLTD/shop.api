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

function checkError($handle = null)
{
    if (se_db_error()) {
        $status['status'] = 'error';
        $status['error'] = 'Ошибка при импорте: ' . se_db_error();
        outputData($status);
        if ($handle)
            fclose($handle);
        se_db_query("ROLLBACK");
        exit;
    }
}

function getIdBrandByName($name)
{
    $u = new seTable('shop_brand');
    $u->select("id");
    $u->where('name = "?"', $name);
    $result = $u->fetchOne();
    if (!empty($result["id"]))
        return $result["id"];

    $u = new seTable('shop_brand');
    $u->name = $name;
    $u->code = strtolower(se_translite_url($name));
    return $u->save();
}

function getCode($code, $table, $fieldCode, $codes = null)
{
    $code_n = $code;
    $u = new seTable($table, 't');
    $i = 1;
    while ($i < 1000) {
        $u->findlist("{$fieldCode}='$code_n'")->fetchOne();
        if ($u->id || in_array($code_n, $codes))
            $code_n = $code . "-$i";
        else return $code_n;
        $i++;
    }
    return uniqid();
}


function getGroup($groups, $idGroup)
{
    if (!$idGroup)
        return;

    foreach ($groups as $group) {
        if ($group["id"] == $idGroup) {
            if ($group['upid'])
                return getGroup($groups, $group['upid']) . "/" . trim($group["name"]);
            else return trim($group["name"]);
        }
    }
}

function getGroup53($groups, $idGroup)
{
    if (!$idGroup)
        return;

    foreach ($groups as $group) {
        if ($group["id"] == $idGroup)
            return trim($group["name"]);
    }
}

function createGroup(&$groups, $idParent, $name)
{
    foreach ($groups as $group) {
        if ($group['upid'] == $idParent && trim($group['name']) == trim($name))
            return $group['id'];
    }

    $u = new seTable('shop_group', 'sg');
    $u->code_gr = getCode(strtolower(se_translite_url(trim($name))), 'shop_group', 'code_gr');
    $u->name = trim($name);
    if ($idParent)
        $u->upid = $idParent;
    $id = $u->save();

    $group = array();
    $group["id"] = $id;
    $group['name'] = trim($name);
    $group["code_gr"] = $u->code_gr;
    $group['upid'] = $idParent;
    $groups[] = $group;

    return $id;
}


function getLevel($id)
{
    global $DBH;

    $level = 0;
    $sqlLevel = 'SELECT `level` FROM shop_group_tree WHERE id_parent = :id_parent AND id_child = :id_parent LIMIT 1';
    $sth = $DBH->prepare($sqlLevel);
    $params = array("id_parent" => $id);
    $answer = $sth->execute($params);
    if ($answer !== false) {
        $items = $sth->fetchAll(PDO::FETCH_ASSOC);
        if (count($items))
            $level = $items[0]['level'];
    }
    return $level;
}

function saveIdParent($id, $idParent)
{
    global $DBH;

    $level = 0;
    $sqlGroupTree = "INSERT INTO shop_group_tree (id_parent, id_child, `level`)
                            SELECT id_parent, :id, `level` FROM shop_group_tree
                            WHERE id_child = :id_parent
                            UNION ALL
                            SELECT :id, :id, :level";
    $sthGroupTree = $DBH->prepare($sqlGroupTree);
    if (!empty($idParent)) {
        $level = getLevel($idParent);
        $level++;
    }
    $sthGroupTree->execute(array('id_parent' => $idParent, 'id' => $id, 'level' => $level));
}

function createGroup53(&$groups, $idParent, $name)
{
    foreach ($groups as $group) {
        if ($group['upid'] == $idParent && $group['name'] == $name)
            return $group['id'];
    }

    $u = new seTable('shop_group', 'sg');
    $u->code_gr = getCode(strtolower(se_translite_url($name)), 'shop_group', 'code_gr');
    $u->name = $name;
    $id = $u->save();

    $group = array();
    $group["id"] = $id;
    $group['name'] = $name;
    $group["code_gr"] = $u->code_gr;
    $group['upid'] = $idParent;
    $groups[] = $group;

    saveIdParent($id, $idParent);

    return $id;
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

$fields = ["id" => "Ид.", "article" => "Артикул", "code" => "Код (URL)", "id_group" => "Ид. категории",
    "code_group" => "Код категории", "catalog0" => "Корневая категория", "catalog1" => "Подкатегория 1",
    "catalog2" => "Подкатегория 2", "catalog3" => "Подкатегория 3", "catalog4" => "Подкатегория 4",
    "path_group" => "Путь категории", "name" => "Наименование", "price" => "Цена пр.", "price_opt" => "Цена опт.",
    "price_opt_corp" => "Цена корп.", "price_purchase" => "Цена закуп.", "presence_count" => "Остаток",
    "brand" => "Бренд", "weight" => "Вес", "volume" => "Объем", "measure" => "Ед.Изм", "note" => "Краткое описание",
    "text" => "Полное описание", "curr" => "Код валюты", "title" => "Тег title", "keywords" => "Мета-тег keywords",
    "description" => "Мета-тег description", "img" => "Фото 1", "img_2" => "Фото 2",
    "img_3" => "Фото 3", "img_4" => "Фото 4", "img_5" => "Фото 5", "img_6" => "Фото 6",
    "img_7" => "Фото 7", "img_8" => "Фото 8", "img_9" => "Фото 9", "img_10" => "Фото 10"];

$addFields = ["file_1" => "Документ 1", "file_2" => "Документ 2", "file_3" => "Документ 3"];

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
        $fileCSV = "{$dir}/catalog.csv";
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
    $samples = array();

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
    $cols = array();
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
    $t->orderBy("sf.id");
    $features = $t->getList();
    foreach ($features as $feature)
        $fields[] = $feature["name"];

    foreach ($addFields as $key => $addField)
        $fields[] = $addField;

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
    $products = array();

    $colsProducts = array();
    $fieldsKeys = array_flip(array_merge($fields, $addFields));
    $result = se_db_query("SHOW COLUMNS FROM shop_price");
    while ($row = se_db_fetch_row($result))
        $colsProducts[] = $row[0];

    $fieldsGroups = ["id_group", "code_group", "catalog0", "path_group"];

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
    $groups = $u->getList();
    $idsGroups = array();
    $idsGroupsByCode = array();
    foreach ($groups as $group) {
        $idsGroups[] = $group["id"];
        $idsGroupsByCode[$group["code_gr"]] = $group["id"];
    }

    $features = array();
    $t = new seTable("shop_feature", "sf");
    $t->select("sf.id, sf.name, sf.type");
    $t->orderBy("sf.id");
    $items = $t->getList();
    foreach ($items as $item) {
        $features[$item["name"]] = $item;
        $fieldsKeys[$item["name"]] = $item["name"];
    }

    if (($handle = fopen($fileCSV, "r")) !== false) {
        $i = 0;

        se_db_query("SET AUTOCOMMIT=0; START TRANSACTION");
        while (($row = fgetcsv($handle, 16000, $separator)) !== false) {
            if ($i++ < $skip)
                continue;

            $product = array();

            foreach ($row as $index => $value) {

                if ($index >= count($cols))
                    continue;
                $col = $cols[$index];
                if (empty($col))
                    continue;

                if ($encoding != "UTF-8")
                    $value = iconv('CP1251', 'UTF-8', $value);

                if (!empty($fieldsKeys[$col]))
                    $product[$fieldsKeys[$col]] = $value;
            }


            if (empty($product[$keyField]) && empty($product["name"]))
                continue;

            // поиск Id группы
            $isExistFieldGroup = false;
            foreach ($fieldsGroups as $field)
                if ($isExistFieldGroup = key_exists($field, $product))
                    break;
            if ($isExistFieldGroup) {
                if (empty($product["id_group"])) {
                    if (empty($product["code_group"])) {
                        $path = $product["path_group"];
                        if (empty($path)) {
                            if (!empty($product["catalog0"])) {
                                for ($i = 0; $i < 5; $i++) {
                                    $path .= $product["catalog{$i}"] . "/";
                                    if (empty($product["catalog{$i}"]))
                                        break;
                                }
                                $path = trim($path, "/");
                            }
                        }
                        if ($path) {
                            $path = str_replace("/ ", "/", $path);
                            $names = explode("/", $path);
                            $idGroup = null;
                            foreach ($names as $name) {
                                if (CORE_VERSION == "5.3")
                                    $idGroup = createGroup53($groups, $idGroup, $name);
                                else $idGroup = createGroup($groups, $idGroup, $name);
                            }
                            $product["id_group"] = $idsGroups[] = $idGroup;
                        }
                    } else $product["id_group"] = $idsGroupsByCode[$product["code_group"]];
                    if (!in_array($product["id_group"], $idsGroups))
                        unset($product["id_group"]);
                }
            }

            $result = null;
            $isNew = false;

            if (!empty($product[$keyField])) {
                $t = new seTable("shop_price");
                $t->select("id");
                $t->where("{$keyField} = '?'", $product[$keyField]);
                $result = $t->fetchOne();
            }

            if (!empty($product["brand"]))
                $product["id_brand"] = getIdBrandByName($product["brand"]);

            if (!empty($result)) {
                $t = new seTable("shop_price");
                $product["id"] = $result["id"];
                $isUpdate = false;

                foreach ($product as $field => $value)
                    if (in_array($field, $colsProducts))
                        $isUpdate |= setField(false, $t, $value, $field);
                if ($isUpdate) {
                    $t->where("id = ?", $product["id"]);
                    $t->save();
                }
                if (!se_db_error())
                    $countUpdate++;
                $isNew = false;
            } else {
                $t = new seTable("shop_price");
                $isInsert = false;

                foreach ($product as $field => $value)
                    if (in_array($field, $colsProducts)) {
                        if ($field == "code" && !empty($value))
                            $value = getCode($value, "shop_price", "code");
                        $isInsert |= setField(true, $t, $value, $field);
                    }
                $isInsert = $isInsert && !empty($product["name"]);
                if ($isInsert) {
                    if (empty($product["code"])) {
                        if (empty($product["name"]))
                            $product["code"] = uniqid();
                        else $product["code"] = strtolower(se_translite_url($product["name"]));
                        $product["code"] = getCode($product["code"], "shop_price", "code");
                        $isInsert |= setField(true, $t, $product["code"], "code");
                    }
                    $product["id"] = $t->save();
                    $isNew = true;
                }
                if ($isInsert && !se_db_error())
                    $countInsert++;
            }

            checkError($handle);

            // категории товаров для ядра 5.3
            if (!empty($product["id_group"])) {
                $isCreate = true;
                if (!$isNew) {
                    $t = new seTable("shop_price_group", "spg");
                    $t->select("spg.id");
                    $t->where("spg.id_group = {$product['id_group']} AND spg.id_price = {$product['id']}");
                    $result = $t->fetchOne();
                    if (!empty($result))
                        $isCreate = false;
                    if ($isCreate) {
                        $t = new seTable("shop_price_group");
                        $t->where("spg.id_price = {$product['id']} AND is_main")->deleteList();
                    }
                }
                if ($isCreate) {
                    $t = new seTable("shop_price_group");
                    $t->id_group = $product["id_group"];
                    $t->id_price = $product["id"];
                    $t->save();
                }
            }

            checkError($handle);

            // фотографии товаров
            if (!empty($product["img"])) {
                $product["img_0"] = $product["img"];
                for ($i = 0; $i < 11; $i++) {
                    $picture = $product["img_{$i}"];
                    if (empty($picture))
                        continue;

                    $t = new seTable("shop_img", "si");
                    $t->select("si.id");
                    $t->where("id_price = ? AND picture = '{$picture}'", $product["id"]);
                    $result = $t->fetchOne();
                    if (!empty($result))
                        continue;

                    $t = new seTable("shop_img", "si");
                    $t->id_price = $product["id"];
                    $t->picture = $picture;
                    $t->picture_alt = $product["name"] . " " . ($i + 1);
                    $t->title = $product["name"] . " " . ($i + 1);
                    $t->sort = $i;
                    $t->save();
                }
            }

            checkError($handle);

            // файлы товаров
            if (!empty($product["file_1"])) {
                for ($i = 1; $i < 4; $i++) {
                    if (empty($product["file_{$i}"]))
                        continue;

                    $fileDoc = $product["file_{$i}"];

                    $t = new seTable("shop_files", "sf");
                    $t->select("sf.id");
                    $t->where("id_price = ? AND file = '{$fileDoc}'", $product["id"]);
                    $result = $t->fetchOne();
                    if (!empty($result))
                        continue;

                    $ext = end(explode(".", $fileDoc));
                    preg_match('|\((.+)\)|', $fileDoc, $matches);
                    $fileDoc = trim(str_replace($matches[0], "", $fileDoc));
                    $nameDoc = trim($matches[1]);

                    $t = new seTable("shop_files", "sf");
                    $t->id_price = $product["id"];
                    $t->file = $fileDoc;
                    $t->name = $nameDoc ? $nameDoc : str_replace(".{$ext}", "", $fileDoc);
                    $t->save();
                }
            }


            // параметры
            foreach ($features as $name => $feature) {
                if (empty($product[$name]))
                    continue;

                $idMod = null;
                $isNewValue = true;
                $t = new seTable("shop_modifications_feature", "smf");
                $t->select("smf.id");
                $t->where("smf.id_price = ? AND smf.id_feature = {$feature['id']}", $product["id"]);
                $result = $t->fetchOne();
                if ($result) {
                    $idMod = $result["id"];
                    $isNewValue = false;
                }

                $t = new seTable("shop_modifications_feature");
                setField($isNewValue, $t, $product["id"], "id_price");
                setField($isNewValue, $t, $feature["id"], "id_feature");

                if (($feature["type"] == "list") || ($feature["type"] == "colorlist")) {
                    $idValue = null;
                    $u = new seTable("shop_feature_value_list", "sfvl");
                    $u->select("sfvl.id");
                    $u->where("sfvl.id_feature = {$feature['id']} AND value = '?'", $product[$name]);
                    $result = $u->fetchOne();
                    if ($result)
                        $idValue = $result["id"];
                    else {
                        $u = new seTable("shop_feature_value_list");
                        setField(true, $u, $feature["id"], "id_feature");
                        setField(true, $u, $product[$name], "value");
                        $idValue = $u->save();
                        checkError($handle);
                    }
                    setField($isNewValue, $t, $idValue, "id_value");

                } else {
                    switch ($feature["type"]) {
                        case 'number' :
                            setField($isNewValue, $t, $product[$name], "value_number");
                            break;
                        case 'bool' :
                            setField($isNewValue, $t, (bool) $product[$name], "value_bool");
                            break;
                        case 'string' :
                            setField($isNewValue, $t, $product[$name], "value_string");
                            break;
                    }
                }
                if ($idMod)
                    $t->where("id = ?", $idMod);
                $t->save();
            }

            checkError($handle);
        }
    }
    fclose($handle);

    se_db_query("COMMIT");

    $status['status'] = 'ok';
    $status['data'] = ["countInsert" => (int)$countInsert, "countUpdate" => (int)$countUpdate];
    outputData($status);

}
