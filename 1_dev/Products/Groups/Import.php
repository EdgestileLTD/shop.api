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

function getCode($code)
{
    $code_n = $code;
    $u = new seTable('shop_group', 'sg');
    $i = 1;
    while ($i < 1000) {
        $u->findlist("sg.code_gr='$code_n'")->fetchOne();
        if ($u->id)
            $code_n = $code . "-$i";
        else return $code_n;
        $i++;
    }
    return uniqid();
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

    $DBH->query("DELETE FROM shop_group_tree WHERE id_child = {$id}");

    $level = 0;
    $sqlGroupTree = "INSERT INTO shop_group_tree (id_parent, id_child, `level`)
                            SELECT id_parent, :id, :level FROM shop_group_tree
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

$fields = ["Ид.", "Код (URL)", "Ид. надгруппы", "Код (URL) надгруппы", "Наименование", "Краткое описание",
    "Полное описание", "Фото", "Тег title", "Мета-тег keywords", "Мета-тег description"];

$keyFields = ["Идентификатор" => "id", "Код (URL)" => "code_gr", "Наименование" => "name"];

if ($step == 0) {

    $filePath = $dir . "/" . $_FILES["file"]["name"];

    if (!move_uploaded_file($_FILES["file"]['tmp_name'], $filePath)) {
        $status['status'] = 'error';
        $status['error'] = 'Не удаётся загрузить файл для импорта!';
        outputData($status);
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

    $_SESSION["import"]["group"]["delimiter"] = $delimiter;
    $_SESSION["import"]["group"]["separator"] = $separator;
    $_SESSION["import"]["group"]["encoding"] = $encoding;
    $_SESSION["import"]["group"]["key"] = $keyField;
    $_SESSION["import"]["group"]["enclosure"] = $enclosure;
    $_SESSION["import"]["group"]["skip"] = $skip;

    $status['status'] = 'ok';
    $status['data'] = ["cols" => $cols, "fields" => $fields];

    outputData($status);
}

if ($step == 1) {

    $separator = $_SESSION["import"]["group"]["separator"];
    $encoding = $_SESSION["import"]["group"]["encoding"];
    $keyUser = $_SESSION["import"]["group"]["key"];
    $enclosure = $_SESSION["import"]["group"]["enclosure"];
    $skip = $_SESSION["import"]["group"]["skip"];
    $keyField = $keyFields[$keyUser];

    $fieldsTable = ["id", "code_gr", "upid", "code_parent", "name", "commentary",
        "footertext", "picture", "title", "keywords", "description"];

    $_SESSION["import"]["group"]["cols"] = $cols = $json->listValues;
    $fileCSV = "{$dir}/groups.csv";
    $countInsert = 0;
    $countUpdate = 0;
    $groups = array();


    if (($handle = fopen($fileCSV, "r")) !== false) {
        $i = 0;

        se_db_query("SET AUTOCOMMIT=0; START TRANSACTION");
        while (($row = fgetcsv($handle, 16000, $separator)) !== false) {
            if ($i++ < $skip)
                continue;

            $group = array();
            foreach ($row as $index => $value) {

                if ($index >= count($cols))
                    continue;
                $col = $cols[$index];
                if (empty($col))
                    continue;

                if ($encoding != "UTF-8")
                    $value = iconv('CP1251', 'UTF-8', $value);

                $fieldsKeys = array_flip($fields);
                $group[$fieldsTable[$fieldsKeys[$col]]] = $value;
            }

            if (empty($group[$keyField]) && empty($group["name"]))
                continue;

            $result = null;
            if (!empty($group[$keyField])) {
                $t = new seTable("shop_group");
                $t->select("id");
                $t->where("{$keyField} = '?'", $group[$keyField]);
                $result = $t->fetchOne();
            }
            if (!empty($result)) {
                $t = new seTable("shop_group");
                $group["id"] = $result["id"];
                $isUpdate = false;
                foreach ($group as $field => $value)
                    if (!in_array($field, ["code_parent", "upid"]))
                        $isUpdate |= setField(false, $t, $value, $field);
                if ($isUpdate) {
                    $t->where("id = ?", $group["id"]);
                    $t->save();
                }
                if (!se_db_error())
                    $countUpdate++;
            }
            else {
                $t = new seTable("shop_group", "sg");
                $isInsert = false;

                foreach ($group as $field => $value)
                    if (!in_array($field, ["code_parent", "upid"]))
                        $isInsert |= setField(true, $t, $value, $field);
                if ($isInsert) {
                    if (empty($group["code_gr"])) {
                        if (empty($group["name"]))
                            $group["code_gr"] = uniqid();
                        else {
                            $group["code_gr"] = strtolower(se_translite_url($group["name"]));
                            $group["code_gr"] = getCode($group["code_gr"]);
                        }
                        $isInsert |= setField(true, $t, $group["code_gr"], "code_gr");
                    }
                    $group["id"] = $t->save();
                    if ($group["id"])
                        $countInsert++;
                }
            }

            if (se_db_error()) {
                $status['status'] = 'error';
                $status['error'] = 'Ошибка при импорте: ' . se_db_error();
                outputData($status);
                fclose($handle);
                se_db_query("ROLLBACK");
                exit;
            }

            $groups[] = $group;
        }
    }
    fclose($handle);

    se_db_query("COMMIT");

    foreach ($groups as $group) {
        $idParent = null;
        $idGroup = $group["id"];
        if (empty($group["upid"]) && empty($group["code_parent"])) {
            saveIdParent($idGroup, $idParent);
            continue;
        }

        if (!empty($group["upid"])) {
            $t = new seTable("shop_group");
            $t->select("id");
            $t->where("id = ?", $group["upid"]);
            $result = $t->fetchOne();
            if (empty($result))
                break;

            $idParent = $result["id"];

        } elseif (!empty($group["code_parent"])) {
            $t = new seTable("shop_group");
            $t->select("id");
            $t->where("code_gr = '?'", $group["code_parent"]);

            $result = $t->fetchOne();
            if (empty($result))
                break;

            $idParent = $result["id"];
        }

        $t = new seTable("shop_group");
        if ($idGroup != $idParent)
            $isUpdated |= setField(false, $t, $idParent, 'upid');
        else $isUpdated |= setField(false, $t, "", 'upid');
        $t->where("id = ?", $idGroup);
        $t->save();

        if (CORE_VERSION != "5.2")
            saveIdParent($idGroup, $idParent);

    }

    $status['status'] = 'ok';
    $status['data'] = ["countInsert" => (int) $countInsert, "countUpdate" => (int) $countUpdate];

    outputData($status);

}
