<?php

if (IS_EXT) {
    require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/PHPExcel.php';
    require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/PHPExcel/Writer/Excel2007.php';
} else {
    require_once $_SERVER['DOCUMENT_ROOT'] . '/api/lib/PHPExcel/Classes/PHPExcel.php';
    require_once $_SERVER['DOCUMENT_ROOT'] . '/api/lib/PHPExcel/Classes/PHPExcel/Writer/Excel2007.php';
}

function getDelimiter($file)
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
$delimiter = isset($_GET['delimiter']) ? $_GET['delimiter'] : ";";
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
        $delimiter = getDelimiter($fileCSV);
    } elseif ($delimiter == "Точка с запятой") {
        $delimiter = ";";
    } elseif ($delimiter == "Запятая") {
        $delimiter = ",";
    } else $delimiter = "\t";

    $count = 0;
    $maxHeaderRows = 25;
    $samples = [];
    if (($handle = fopen($fileCSV, "r")) !== false) {
        $i = 0;
        while (($row = fgetcsv($handle, 16000, $delimiter)) !== false &&
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

    $_SESSION["import"]["group"]["delimiter"] = $delimiter;
    $_SESSION["import"]["group"]["encoding"] = $encoding;
    $_SESSION["import"]["group"]["key"] = $keyField;
    $_SESSION["import"]["group"]["enclosure"] = $enclosure;
    $_SESSION["import"]["group"]["skip"] = $skip;

    $status['status'] = 'ok';
    $status['data'] = ["cols" => $cols, "fields" => $fields];

    outputData($status);
}

if ($step == 1) {

    $delimiter = $_SESSION["import"]["group"]["delimiter"];
    $encoding = $_SESSION["import"]["group"]["encoding"];
    $keyUser = $_SESSION["import"]["group"]["key"];
    $enclosure = $_SESSION["import"]["group"]["enclosure"];
    $skip = $_SESSION["import"]["group"]["skip"];
    $keyField = $keyFields[$keyUser];

    $fieldsTable = ["id", "code_gr", "upid", "code_parent", "name", "commentary",
        "footertext", "picture", "title", "keywords", "description"];

    $cols = $json->listValues;
    $fileCSV = "{$dir}/groups.csv";
    $countInsert = 0;
    $countUpdate = 0;
    $groups = [];

    if (($handle = fopen($fileCSV, "r")) !== false) {
        $i = 0;

        while (($row = fgetcsv($handle, 16000, $delimiter)) !== false) {
            if ($i++ < $skip)
                continue;

            $group = [];
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

            if (empty($group[$keyField]))
                continue;

            $t = new seTable("shop_group");
            $t->select("id");
            $t->where("{$keyField} = '?'", $group[$keyField]);
            $result = $t->fetchOne();
            if (!empty($result)) {
                $group["id"] = $result["id"];
                $isUpdate = false;
                foreach ($group as $field => $value)
                    if (!in_array($field, ["code_parent", "upid"]))
                        $isUpdate |= setField(false, $t, $value, $field);
                if ($isUpdate)
                    $t->save();
                if (!se_db_error())
                    $countUpdate++;
            }
            else {
                $t = new seTable("shop_group", "sg");
                $isInsert = false;
                foreach ($group as $field => $value)
                    if (!in_array($field, ["code_parent", "upid"]))
                        $isInsert |= setField(true, $t, $value, $field);
                if ($isInsert)
                    $group["id"] = $t->save();
                if (!se_db_error())
                    $countInsert++;
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


    foreach ($groups as $group) {
        if (empty($group["upid"]) && empty($group["code_parent"]))
            continue;

        $idParent = null;
        $idGroup = $group["id"];
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
            writeLog($t->getSQL());
            $result = $t->fetchOne();
            if (empty($result))
                break;

            $idParent = $result["id"];
        }
        writeLog(2);
        $t = new seTable("shop_group");
        if ($idGroup != $idParent)
            $isUpdated |= setField(false, $t, $idParent, 'upid');
        else $isUpdated |= setField(false, $t, "", 'upid');
        $t->where("id = ?", $idGroup);
        $t->save();

        if (CORE_VERSION == "5.3")
            saveIdParent($idGroup, $idParent);

    }

    $status['status'] = 'ok';
    $status['data'] = ["countInsert" => (int) $countInsert, "countUpdate" => (int) $countUpdate];
    outputData($status);

}
