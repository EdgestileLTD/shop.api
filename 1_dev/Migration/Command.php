<?php

$json->command = empty($json->command) ? $_GET['command'] : $json->command;

$username = $CONFIG['DBUserName'];
$password = $CONFIG['DBPassword'];
$hostname = $CONFIG['HostName'];
$database = $CONFIG['DBName'];

function createPath($dir)
{
    $root = null;
    if (!file_exists($dir)) {
        $dirs = explode('/', $dir);
        $path = $root;
        foreach ($dirs as $d) {
            $path .= $d;
            if (!file_exists($path))
                mkdir($path, 0700);
            $path .= '/';
        }
    }
}

if ($json->command == "createDump") {

    require_once API_ROOT . "Migration/MySQLDump.php";

    $dump_dir = $_SERVER['DOCUMENT_ROOT'] . "/api/dumps";
    $dump_name = $json->hostname . '.sql.gz';
    $dump = new MySQLDump(new mysqli($hostname, $username, $password, $database));
    $dump->save($dump_dir . "/" . $dump_name);
    echo file_get_contents($dump_dir . "/" . $dump_name);
}

if ($json->command == "restoreDump") {

    $dir = $_SERVER['DOCUMENT_ROOT'] . "/api/restores";
    createPath($dir);
    $fileName = $_FILES['file']['name'];
    $ext = substr(strrchr($fileName, '.'), 1);
    $fileName = $dir . "/" . $fileName;
    if (!move_uploaded_file($_FILES["file"]['tmp_name'], $fileName))
        exit;

    $fp = gzopen($fileName, "r");
    $flagSeparator = false;
    $flagStartString = false;
    $result = true;
    while (!feof($fp)) {
        $ch = fread($fp, 1);
        $query .= $ch;
        $flagStartString = ($ch == "'") && !$flagSplash ? !$flagStartString : $flagStartString;
        if (($ch == ';') && !$flagStartString) {
            $result = $result && mysqli_query($db_link, $query);
            if (!$result)
                break;
            $query = null;
        }
        $flagSplash = $ch == '\\';
    }
    if ($query)
        $result = $result && mysqli_query($db_link, $query);
    fclose($fp);

    if ($result)
        echo "ok";
    else echo "error";
}

if ($json->command == "copyImages") {

    $dirRoot = '/home/e/edgestile/' . $json->hostname . '/public_html/images/rus/';

    $error = null;
    $destProject = strpos($json->destProject, ".e-stile.ru") ? $json->destProject :
        $json->destProject . ".e-stile.ru";
    $folders = explode(";", $json->folders);
    $isOverride = $json->isOverride;
    foreach ($folders as $folder) {

        $sourceFolder = $dirRoot . $folder;
        $destFolder =  '/home/e/edgestile/' . $destProject . "/public_html/images/rus/" . $folder;
        if (!is_dir($sourceFolder))
            continue;
        if (!is_dir($destFolder))
            mkdir($destFolder);
        $sourceDir = opendir($sourceFolder);
        if (!$sourceDir)
            continue;

        while (($file = readdir($sourceDir)) !== false) {
            if (($file == '.' || $file == '..') || (file_exists($destFolder . "/{$file}") && !$isOverride))
                continue;
            copy($sourceFolder . "/{$file}", $destFolder . "/{$file}");
        }
        closedir($sourceDir);
    }

    if (!$error) {
        $status['status'] = 'ok';
        $status['data'] = array();
    } else {
        $status['status'] = 'error';
        $status['errortext'] = $error;
    }
    outputData($status);
}