<?php

header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="contacts.zip"');
header('Content-Transfer-Encoding: binary');

// преоброзование переменных запроса в перемнные БД
function convertFields($str)
{
    $str = str_replace('id ', 'p.id ', $str);
    $str = str_replace('title', 'last_name', $str);
    $str = str_replace('firstName', 'first_name', $str);
    $str = str_replace('lastName', 'last_name', $str);
    $str = str_replace('secondName', 'sec_name', $str);
    $str = str_replace('regDate', 'reg_date', $str);
    $str = str_replace('phone', 'phone', $str);
    $str = str_replace('email', 'email', $str);
    $str = str_replace('isRead', 'is_read', $str);
    $str = str_replace('idGroup', 'sug.group_id', $str);
    $str = str_replace('emailValid', 'email_valid', $str);
    $str = str_replace('display', 'last_name', $str);
    $str = str_replace('login', 'su.username', $str);
    $str = str_replace('company', 'c.name', $str);
    return $str;
}


$format = isset($json->format) ? $json->format : "csv";
if ($format == "xml") {
    $dom = new DomDocument('1.0', 'utf-8');
    $rootDOM = $dom->appendChild($dom->createElement('objects'));
}
$root = API_ROOT;
if (IS_EXT)
    $dir = '../app-data/exports/' . $format . '/contacts';
else $dir = '../app-data/' . $json->hostname . '/exports/' . $format . '/contacts';
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

function createExportFileFromQuery($query, $objectsName, $objectName)
{
    GLOBAL $dir, $dom, $rootDOM, $format;

    if ($format == "xml")
        $objectsDOM = $rootDOM->appendChild($dom->createElement($objectsName));
    $result = se_db_query($query, 0);
    if ($result) {
        $list = array();
        $header = array();
        while ($row = se_db_fetch_assoc($result)) {
            if (!$header) {
                $header = array_keys($row);
                $list[] = $header;
            }
            if ($format == "csv")
                $list[] = $row;
            if ($format == "xml") {
                $objectDOM = $objectsDOM->appendChild($dom->createElement($objectName));
                for ($i = 0; $i < sizeof($row); $i++) {
                    $item = $objectDOM->appendChild($dom->createElement($header[$i]));
                    $item->appendChild($dom->createTextNode($row[$header[$i]]));
                }
            }
        }

        if ($format == "csv") {
            $fileName = "$objectsName.csv";
            $fileName = $dir . '/' . $fileName;
            $fp = fopen($fileName, 'w');
            foreach ($list as $line) {
                foreach ($line as &$str)
                    $str = iconv('utf-8', 'CP1251', $str);
                fputcsv($fp, $line, ";");
            }
            fclose($fp);
        }
    }
}


// ********************* контакты **********************************************************************************
$u = new seTable('person', 'p');
$u->select('p.reg_date RegDateTime, su.username Login, 
            p.last_name Surname, p.first_name Name, p.sec_name Patronymic, 
            p.email Email, p.phone Phone, 
            CASE price_type
                WHEN 0 THEN "Розничая"
                WHEN 1 THEN "Мелкооптовая"         
                ELSE "Оптовая"
            END PriceType, 
            sg.name GroupName,
            su.is_active IsActive,
            p.note Note');
$u->innerjoin('se_user su', 'p.id=su.id');
$u->leftjoin('se_user_group sug', 'p.id=sug.user_id');
$u->leftjoin('se_group sg', 'sug.group_id = sg.id');
$u->groupby('p.id');
$u->orderby('p.id');

if (!empty($json->filter))
    $filter = convertFields($json->filter);
if (!empty($filter))
    $u->where($filter);

createExportFileFromQuery($u->getSql(), "contacts", "contact");

// ********************* архивирование *****************************************************************************
if ($format == "xml") {
    $fileName = "contacts.xml";
    $fileName = $dir . '/' . $fileName;
    $dom->formatOutput = true;
    $dom->save($fileName);
}

// подгружаем библиотеку zip
$zip = new ZipArchive();
$zipName = $dir . '.zip';
if (file_exists($zipName))
    unlink($zipName);
if ($zip->open($zipName, ZIPARCHIVE::CREATE == true)) {
    if ($handle = opendir($dir)) {
        while (false !== ($file = readdir($handle))) {
            if ($file != "." && $file != "..") {
                $zip->addFile($dir . '/' . $file, $file);
            }
        }
        closedir($handle);
    }
}
$zip->close();

echo file_get_contents($zipName);