<?php

$section = $json->section;
$files = $json->files;

$status = array();
if (!$section) {
    $status['status'] = 'error';
    $status['error'] = "parameter 'section' isn't  set";
    echo json_encode($status);
}

if (IS_EXT)
    $dirRoot = $_SERVER['DOCUMENT_ROOT'];
else $dirRoot = '/home/e/edgestile/' . $json->hostname . '/public_html';

$lang = $_SESSION['language'] ? $_SESSION['language'] : 'rus';
$lang = FOLDER_SHOP ? FOLDER_SHOP : $lang;

$isUnused = (bool)$json->isUnused; // неиспользуемые изображения
$usedImages = array();

if ($section == 'shopprice' && $isUnused) {
    $u = new seTable('shop_price', 'sp');
    $u->select('img name');
    $images = $u->getList();
    foreach ($images as $image)
        if ($image['name'])
            $usedImages[] = $image['name'];
    $u = new seTable('shop_img', 'si');
    $u->select('picture name');
    $images = $u->getList();
    foreach ($images as $image)
        if ($image['name'])
            $usedImages[] = $image['name'];
}
if ($section == 'shopgroup' && $isUnused) {

    $u = new seTable('shop_group', 'sg');
    $u->select('picture name');
    $images = $u->getList();
    foreach ($images as $image)
        if ($image['name'])
            $usedImages[] = $image['name'];
    $u = new seTable('shop_img', 'si');
    $u->select('picture name');
    $images = $u->getList();
    foreach ($images as $image)
        if ($image['name'])
            $usedImages[] = $image['name'];
}
if ($section == 'newsimg' && $isUnused) {
    $u = new seTable('news', 'n');
    $u->select('img name');
    $images = $u->getList();
    foreach ($images as $image)
        if ($image['name'])
            $usedImages[] = $image['name'];
    $u = new seTable('news_img', 'ni');
    $u->select('picture name');
    $images = $u->getList();
    foreach ($images as $image)
        if ($image['name'])
            $usedImages[] = $image['name'];
}
if ($section == 'shopbrand' && $isUnused) {
    $u = new seTable('shop_brand', 'sb');
    $u->select('image name');
    $images = $u->getList();
    foreach ($images as $image)
        if ($image['name'])
            $usedImages[] = $image['name'];
}

$status = array();
if (!empty($section)) {
    $dir = $dirRoot . "/images/$lang/$section";
    if ($isUnused) {
        $handleDir = opendir($dir);
        while (($file = readdir($handleDir)) !== false) {
            if ($file == '.' || $file == '..')
                continue;
            if (!in_array($file, $usedImages))
                unlink($dir . "/" . $file);
        }
    } else
        foreach ($files as $file)
            if (!empty($file))
                unlink($dir . "/" . $file);

    $status['status'] = 'ok';
    $status['data'] = null;
    file_get_contents("http://" . $json->hostname . "/lib/image.php?deletecache");
} else {
    $status['status'] = 'error';
    $status['error'] = "Не удаётся удалить файлы изображений!";
}

outputData($status);