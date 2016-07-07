<?php

$status = array();
if (!$json->section) {
    $status['status'] = 'error';
    $status['errortext'] = "Не указан параметр: section";
    echo json_encode($status);
}

if (IS_EXT)
    $dirRoot = $_SERVER['DOCUMENT_ROOT'];
else $dirRoot = '/home/e/edgestile/' . $json->hostname . '/public_html';
$section = $json->section;
$lang = $_SESSION['language'] ? $_SESSION['language'] : 'rus';
$lang = FOLDER_SHOP ? FOLDER_SHOP : $lang;
$listFiles = array();

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
    if ($idAlbum) {
        $result = $api->getPhotos($idAlbum, $json->limit, $json->offset);
        $count = $result["count"];
        $listFiles = $result["list"];
    }

} else {

    if (function_exists("mb_strtolower"))
        $searchStr = mb_strtolower(trim($json->searchText));
    else $searchStr = strtolower(trim($json->searchText));
    if ($searchStr)
        $json->offset = 0;
    $listFiles = array();
    $dir = $dirRoot . "/images/$lang/$section";
    if (file_exists($dir) && is_dir($dir)) {
        $handleDir = opendir($dir);
        $count = 0;
        $i = 0;
        while (($file = readdir($handleDir)) !== false) {
            if ($file == '.' || $file == '..')
                continue;
            if ($searchStr && (strpos(mb_strtolower($file), $searchStr) === false))
                continue;
            $count++;
            if ($i++ < $json->offset)
                continue;

            if ($count <= $json->limit + $json->offset) {
                $item = array();
                $item["name"] = $file;
                $item["title"] = $file;
                $item["weight"] = number_format(filesize($dir . "/" . $file), 0, '', ' ');
                list($width, $height, $type, $attr) = getimagesize($dir . "/" . $file);
                $item["sizeDisplay"] = $width . " x " . $height;
                $item["imageUrl"] = 'http://' . $json->hostname . "/images/rus/{$section}/" . $file;
                $item["imageUrlPreview"] = "http://{$json->hostname}/lib/image.php?size=64&img=images/rus/{$section}/" . $file;
                $listFiles[] = $item;
            }
        }
        closedir($handleDir);
    }
}

$data['count'] = $count;
$data['items'] = $listFiles;

$status['status'] = 'ok';
$status['data'] = $data;

outputData($status);
