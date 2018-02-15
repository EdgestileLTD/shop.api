<?php

$section = ($json->section) ? $json->section : $_GET['section'];

$status = array();
if (!$section) {
    $status['status'] = 'error';
    $status['error'] = "parameter 'section' isn't  set";
    echo json_encode($status);
}

$lang = 'rus';
$lang = FOLDER_SHOP ? FOLDER_SHOP : $lang;

if (IS_EXT)
    $uploadDir = $_SERVER['DOCUMENT_ROOT'];
else $uploadDir = '/home/e/edgestile/' . $json->hostname . '/public_html';
if ($section == "yandexphotos")
    $uploadDir .= "/images/tmp";
else $uploadDir .= "/images/$lang/$section";
mkdir($path, 0700, true);

$countFiles = count($_FILES);
$ups = 0;
$files = array();
$items = array();

for ($i = 0; $i < $countFiles; $i++) {
    $file = $_FILES["file$i"]['name'];
    $uploadFile = $uploadDir . '/' . $file;
    $fileTemp = $_FILES["file$i"]['tmp_name'];
    if (!getimagesize($fileTemp)) {
        $status['error'] = "Ошибка! Найден файл не являющийся изображением!";
        break;
    }
    if (!filesize($fileTemp) || move_uploaded_file($fileTemp, $uploadFile)) {
        if (file_exists($uploadFile)) {
            $files[] = $uploadFile;
            $item = array();
            $item["name"] = $file;
            $item["title"] = $file;
            $item["weight"] = number_format(filesize($uploadFile), 0, '', ' ');
            list($width, $height, $type, $attr) = getimagesize($uploadFile);
            $item["sizeDisplay"] = $width . " x " . $height;
            $item["imageUrl"] = 'http://' . $json->hostname . "/images/rus/{$section}/" . $file;
            $item["imageUrlPreview"] = "http://{$json->hostname}/lib/image.php?size=64&img=images/rus/{$section}/" . $file;
            $items[] = $item;
        }
        $ups++;
    }
}

if ($section == "yandexphotos" && !empty($_SESSION['loginYandex']) && !empty($_SESSION['tokenYandex'])) {

    require_once dirname(__FILE__) . '/yf_autoload.php';

    $idAlbum = $_SESSION['YF_idAlbum'];
    $nameAlbum = "se_" . $json->hostname;

    $api = new YFApi($_SESSION['loginYandex'], $_SESSION['tokenYandex']);
    $albums = $api->getAlbums();
    foreach ($albums as $album)
        if ($album["title"] == $nameAlbum) {
            $idAlbum = $_SESSION['YF_idAlbum'] = $album['id'];
            break;
        }
    if (!$idAlbum)
        $idAlbum = $_SESSION['YF_idAlbum'] = $api->createAlbum($nameAlbum, $nameAlbum);

    $ups = 0;
    $items = array();
    foreach ($files as $file) {
        if ($item = $api->uploadPhoto($idAlbum, $file)) {
            $items[] = $item;
            $ups++;
        }
        unlink($file);
    }
}


if ($ups == $countFiles) {
    $status['status'] = 'ok';
    $status['data'] = array("items" => $items);
} else {
    $status['status'] = 'error';
    if (empty($status['error']))
        $status['error'] = "Не удается загрузить файлы!";
}

outputData($status);


