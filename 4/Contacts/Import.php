<?php

$typeFormatCSV = 0; // 1 - google, 2 - outlook, 3 - abook, 4 - outlook gmail
$suffixOutlook = '"First Name","Middle Name","Last Name"';
$suffixBook = '"Имя","Отчество","Фамилия","Домашний телефон","Рабочий телефон","Телефон дом. 2"';
$suffixOutlookGmail = "First Name,Middle Name,Last Name,Title,Suffix";
$encoding = 'CP1251';
$csvSeparator = ";";

$isRemoveAll = isset($_GET['isClear']) ? $_GET['isClear'] : false;

$root = API_ROOT;
if (IS_EXT)
    $dir = '../app-data/imports/contacts';
else $dir = '../app-data/' . $json->hostname . '/imports/contacts';
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
if (file_exists($dir))
    foreach (glob($dir . '/*') as $file)
        unlink($file);

$zipFile = $dir . "/contacts.zip";
$csvFile = $dir . "/contacts.csv";

if ((!empty($_FILES["file_import"]) && !move_uploaded_file($_FILES["file_import"]['tmp_name'], $zipFile)) ||
    (!empty($_FILES["file"]) && !move_uploaded_file($_FILES["file"]['tmp_name'], $zipFile))
) exit;

$zip = new ZipArchive();
$result = $zip->open($zipFile);
if ($result === TRUE) {
    $zip->extractTo($dir);
    $zip->close();
    unlink($zipFile);
} else {
    $file = file_get_contents($zipFile);
    if (strpos($file, "<?xml") === FALSE) {
        $csvFile = str_replace(".zip", ".csv", $zipFile);
        $tmpFile = str_replace(".zip", ".tmp", $zipFile);
        rename($zipFile, $csvFile);
        if (strlen($file) > 2) {
            $fp = fopen($csvFile, "r");
            $suffix = null;
            for ($i = 0; $i < 2; $i++)
                $suffix .= strtoupper(dechex(Ord(fgetc($fp))));
            if ($suffix == "FFFE") {
                $encoding = "UTF-16";
                $typeFormatCSV = 1;
                $csvSeparator = ",";

            } else fseek($fp, 0);
            $str = null;
            $fw = fopen($tmpFile, "w+");
            while (false !== ($char = fgetc($fp))) {
                $str .= $char;
            }
            $str = iconv($encoding, 'UTF-8', $str);
            fwrite($fw, $str);
            fclose($fw);
            fclose($fp);
            rename($tmpFile, $csvFile);
            if (strpos($str, $suffixOutlook) !== false) {
                $typeFormatCSV = 2;
                $csvSeparator = ",";
            }
            if (strpos($str, $suffixBook) !== false) {
                $typeFormatCSV = 3;
                $csvSeparator = ",";
            }
            if (strpos($str, $suffixOutlookGmail) !== false) {
                $typeFormatCSV = 4;
                $csvSeparator = ",";
            }
        }
    } else rename($zipFile, str_replace(".zip", ".xml", $zipFile));
}

se_db_query("SET AUTOCOMMIT=0; START TRANSACTION");

try {

    function formattedFields($contacts)
    {

        global $typeFormatCSV;
        if ($typeFormatCSV == 0)
            return $contacts;

        $result = array();
        $templates = array(
            "Given Name" => "Name", "First Name" => "Name", "Имя" => "Name",
            "Family Name" => "Surname", "Last Name" => "Surname", "Фамилия" => "Surname",
            "Additional Name" => "Patronymic", "Middle Name" => "Patronymic", "Отчество" => "Patronymic",
            "E-Mail 1 - Value" => "Email", "E-Mail Address" => "Email", "E-mail Address" => "Email", "Адрес эл. почты" => "Email",
            "Phone 1 - Value" => "Phone", "Primary Phone" => "Phone", "Домашний телефон" => "Phone",
            "Mobile Phone" => "AddPhone", "Рабочий телефон" => "AddPhone",
            "Notes" => "Note");
        foreach ($contacts as $item) {
            $newItem = array();
            foreach ($item as $field => $value)
                if ($field) {
                    if (array_key_exists($field, $templates)) {
                        $newItem[$templates[$field]] = $value;
                    } else $newItem[$field] = $value;
                }
            $result[] = $newItem;
        }
        return $result;
    }


    function multi_query_ex($table, $data = array(), $keys = null, $fieldsUpdates = null)
    {
        global $db_link;

        if (!$keys)
            $keys[] = 'id';
        if (!is_array($keys))
            $keys = array($keys);
        $strKeys = null;
        foreach ($keys as $key) {
            if ($strKeys)
                $strKeys .= ',';
            $strKeys .= '`' . $key . '`';
        }

        $listKeys = array();
        foreach ($keys as $key) {
            foreach ($data as $item) {
                if (!empty($item[$key]))
                    $listKeys[$key][] = "'" . $item[$key] . "'";
            }
        }

        $updates = array();
        if (count($listKeys)) {
            $querySuffix = null;
            foreach ($keys as $key) {
                if ($querySuffix)
                    $querySuffix .= " AND ";
                $querySuffix .= "`{$key}` IN (" . join(',', $listKeys[$key]) . ")";
            }
            $res = mysqli_query($db_link, "SELECT {$strKeys} FROM `{$table}` WHERE {$querySuffix}");
            while ($row = mysqli_fetch_assoc($res))
                $updates[] = $row;
        }

        // Получаем подтверждение на обновление
        $query = null;
        foreach ($data as $item) {
            $fields = array();
            $values = array();
            foreach ($item as $field => $value) {
                $fields[] = $field;
                if (is_bool($value) || is_numeric($value) || is_null($value)) {
                    if (is_bool($value) || is_int($value))
                        $values[] = $value;
                    elseif (is_null($value))
                        $values[] = 'NULL';
                    else $values[] = "'" . $value . "'";
                } else {
                    if (is_string($value) && !empty($value))
                        $values[] = "'" . se_db_input($value) . "'";
                    else $values[] = 'NULL';
                }
            }
            $isUpdate = false;
            foreach ($updates as $update) {
                $f = true;
                foreach ($keys as $key) {
                    $f &= ($update[$key] == $item[$key]);
                }
                $isUpdate |= $f;
            }
            if ($isUpdate) {
                $prefix = "UPDATE `{$table}` SET ";
                $suffix = null;
                $where = null;
                foreach ($fields as $id => $fld) {
                    if (!$fieldsUpdates || in_array($fld, $fieldsUpdates)) {
                        if (!empty($suffix))
                            $suffix .= ',';
                        $suffix .= '`' . $fld . '`=' . $values[$id];
                    }
                }
                foreach ($keys as $key) {
                    if (!empty($where))
                        $where .= ' AND ';
                    $where .= "`{$key}`='{$item[$key]}'";
                }
                if ($suffix)
                    $query .= $prefix . $suffix . " WHERE {$where};\n";
            } else {
                $fieldsStr = null;
                foreach ($fields as $field) {
                    if ($fieldsStr)
                        $fieldsStr .= ",";
                    $fieldsStr .= '`' . $field . '`';
                }
                $query .= "INSERT INTO `{$table}`({$fieldsStr}) VALUES (" . join(',', $values) . ");\n";
            }
        }
        if (mysqli_multi_query($db_link, $query))
            while (mysqli_next_result($db_link)) {
                ;
            }

        $ids = array();
        $query = "SELECT id, {$strKeys} FROM `{$table}`";
        if ($result = mysqli_query($db_link, $query)) {
            while ($row = mysqli_fetch_row($result)) {
                $values = array();
                for ($i = 1; $i < count($row); ++$i)
                    $values[] = $row[$i];
                $ids[$row[0]] = $values;
            }
        }
        return $ids;
    }

    if ($isRemoveAll) {
        se_db_query("SET foreign_key_checks = 0");
        se_db_query("TRUNCATE TABLE se_user");
        se_db_query("TRUNCATE TABLE se_group");
        se_db_query("TRUNCATE TABLE se_user_group");
        se_db_query("TRUNCATE TABLE person");
        se_db_query("SET foreign_key_checks = 1");
        if (se_db_error())
            throw new Exception(se_db_error());
    }

    function getArrayFromCsv($file)
    {
        global $csvSeparator;
        if (!file_exists($file))
            return;

        $result = array();
        if (($handle = fopen($file, "r")) !== FALSE) {
            $i = 0;
            $keys = array();
            while (($row = fgetcsv($handle, 10000, $csvSeparator)) !== FALSE) {
                if (!$i) {
                    foreach ($row as &$item)
                        $keys[] = $item;
                } else {
                    $object = array();
                    $j = 0;
                    foreach ($row as $item) {
                        $object[$keys[$j]] = $item;
                        $j++;
                    }
                    $result[] = $object;
                }
                $i++;
            }
            fclose($handle);
        }

        return $result;
    }

    function getArrayFromXml($object)
    {
        $result = array();
        foreach ($object->children() as $item) {
            $object = array();
            foreach ($item->children() as $field) {
                $val = trim((string)$field);
                $object[$field->getName()] = $val;
            }
            $result[] = $object;
        }
        return $result;
    }

    function importContacts($contacts)
    {
        $contacts = formattedFields($contacts);
        $data = array();
        foreach ($contacts as &$item) {
            $username = $item['Login'];
            if (!$username)
                $username = $item['Email'];
            if (!$username)
                $username = $item['Phone'];
            if (!$username)
                $username = uniqid("user");
            $item["Login"] = $username;
            $dit['username'] = $item["Login"];
            $data[] = $dit;
        }
        $keys = multi_query_ex('se_user', $data, 'username', ['username']);
        $idsContacts = array();
        foreach ($keys as $key => $values)
            $idsContacts[$values[0]] = $key;
        $data = array();

        foreach ($contacts as &$item) {
            $dit = array();
            $dit['id'] = $idsContacts[$item['Login']];
            if (!$dit['id'])
                continue;
            if (!empty($item['RegDateTime']))
                $dit['reg_date'] = date("Y-m-d H:i:s", strtotime($item['RegDateTime']));
            else $dit['reg_date'] = date("Y-m-d H:i:s", time());
            if (!empty($item['BirthDate']) && $item['BirthDate'] != "0000-00-00")
                $dit['birth_date'] = date("Y-m-d", strtotime($item['BirthDate']));
            $dit['last_name'] = $item['Surname'];
            $dit['first_name'] = $item['Name'];
            if (!$dit['first_name'] && isset($item['Nickname']))
                $dit['first_name'] = $item['Nickname'];
            $dit['sec_name'] = $item['Patronymic'];
            $dit['email'] = $item['Email'];
            $dit['phone'] = $item['Phone'];
            if (!$dit['phone'])
                $dit['phone'] = $item['AddPhone'];
            $dit['note'] = $item['Note'];
            if (!empty($item['Gender']) && ($item['Gender'] == 'M' || $item['Gender'] == 'N' || $item['Gender'] == 'F'))
                $dit['sex'] = $item['Gender'];
            $data[] = $dit;
        }
        multi_query_ex('person', $data, 'id', ['last_name', 'first_name', 'sec_name', 'email', 'phone', 'note']);
    }

    $count = 0;
    $files = array();
    foreach (glob($dir . '/*') as $file) {
        $count++;
        $files[] = $file;
    }

    $contacts = array();

    if ($count == 1 && end(explode(".", $files[0])) == "xml") {
        $str = file_get_contents($dir . "/contacts.xml");
        $xml = new SimpleXMLElement($str);
        foreach ($xml->children() as $object) {
            if ($object->getName() == "contacts")
                $contacts = getArrayFromXml($object);
        }
    } else $contacts = getArrayFromCsv($csvFile);
    if ($contacts)
        importContacts($contacts);

    se_db_query("COMMIT");
    echo "ok";

} catch (Exception $e) {
    se_db_query("ROLLBACK");
    echo "Ошибка импорта данных! Ошибка: " . $e->getMessage();
}


