<?php


if (IS_EXT)
    $uploadDir = $_SERVER['DOCUMENT_ROOT'];
else $uploadDir = '/home/e/edgestile/' . $json->hostname . '/public_html';
$uploadDir .= "/files";
if (!file_exists($uploadDir)) {
    $dirs = explode('/', $uploadDir);
    $path = $root;
    foreach ($dirs as $d) {
        $path .= $d;
        if (!file_exists($path))
            mkdir($path, 0700);
        $path .= '/';
    }
}

$countFiles = count($_FILES);
$ups = 0;
$files = array();
$items = array();

for ($i = 0; $i < $countFiles; $i++) {
    $file = $_FILES["file$i"]['name'];
    $uploadFile = $uploadDir . '/' . $file;
    $fileTemp = $_FILES["file$i"]['tmp_name'];
    if (!filesize($fileTemp) || move_uploaded_file($fileTemp, $uploadFile)) {
        if (file_exists($uploadFile)) {
            $files[] = $uploadFile;
            $item = array();
            $item["name"] = $file;
            $items[] = $item;
        }
        $ups++;
    }
}

if ($ups == $countFiles) {
    $status['status'] = 'ok';
    $status['data'] = array("items" => $items);
} else {
    $status['status'] = 'error';
    if (empty($status['errortext']))
        $status['errortext'] = "Не удается загрузить файлы!";
}

outputData($status);


