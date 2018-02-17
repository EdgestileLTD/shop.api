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
    $u->select('img name, note, text');
    $products = $u->getList();
    foreach ($products as $product) {
        if ($product['name'] && !in_array($product['name'], $usedImages))
            $usedImages[] = $product['name'];
        $media = [];
        if ($product["note"]) {
            preg_match_all('/<img(?:\\s[^<>]*?)?\\bsrc\\s*=\\s*(?|"([^"]*)"|\'([^\']*)\'|([^<>\'"\\s]*))[^<>]*>/i', $product["note"], $media);
        }
        if ($product["text"]) {
            preg_match_all('/<img(?:\\s[^<>]*?)?\\bsrc\\s*=\\s*(?|"([^"]*)"|\'([^\']*)\'|([^<>\'"\\s]*))[^<>]*>/i', $product["text"], $media);
        }
        if (!empty($media[1][0])) {
            if (strpos($media[1][0], $json->hostname))
                $media[1][0] = str_replace("http://" . $json->hostname, "", $media[1][0]);
            if ((strpos($media[1][0], "http:") === false) && (strpos($media[1][0], "https:") === false)) {
                $fileName = trim(str_replace("/images/$lang/$section", "", $media[1][0]), "/");
                if (!in_array($fileName, $usedImages))
                    $usedImages[] = $fileName;
            }
        }
    }

    $u = new seTable('shop_img', 'si');
    $u->select('picture name');
    $images = $u->getList();
    foreach ($images as $image)
        if ($image['name'])
            $usedImages[] = $image['name'];
}
if ($section == 'shopgroup' && $isUnused) {

    $u = new seTable('shop_group', 'sg');
    $u->select('picture name, commentary, footertext');
    $groups = $u->getList();
    foreach ($groups as $group) {
        if ($group['name'])
            $usedImages[] = $group['name'];

        $media = [];
        if ($group["commentary"]) {
            preg_match_all('/<img(?:\\s[^<>]*?)?\\bsrc\\s*=\\s*(?|"([^"]*)"|\'([^\']*)\'|([^<>\'"\\s]*))[^<>]*>/i', $group["commentary"], $media);
        }
        if ($group["footertext"]) {
            preg_match_all('/<img(?:\\s[^<>]*?)?\\bsrc\\s*=\\s*(?|"([^"]*)"|\'([^\']*)\'|([^<>\'"\\s]*))[^<>]*>/i', $group["footertext"], $media);
        }
        if (!empty($media[1][0])) {
            if (strpos($media[1][0], $json->hostname))
                $media[1][0] = str_replace("http://" . $json->hostname, "", $media[1][0]);
            if ((strpos($media[1][0], "http:") === false) && (strpos($media[1][0], "https:") === false)) {
                $fileName = trim(str_replace("/images/$lang/$section", "", $media[1][0]), "/");
                if (!in_array($fileName, $usedImages))
                    $usedImages[] = $fileName;
            }
        }
    }

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
    $u->select('image name, text, content');
    $brands = $u->getList();
    foreach ($brands as $brand) {
        if ($brand['name'])
            $usedImages[] = $brand['name'];

        $media = [];
        if ($brand["text"]) {
            preg_match_all('/<img(?:\\s[^<>]*?)?\\bsrc\\s*=\\s*(?|"([^"]*)"|\'([^\']*)\'|([^<>\'"\\s]*))[^<>]*>/i', $brand["text"], $media);
        }
        if ($brand["content"]) {
            preg_match_all('/<img(?:\\s[^<>]*?)?\\bsrc\\s*=\\s*(?|"([^"]*)"|\'([^\']*)\'|([^<>\'"\\s]*))[^<>]*>/i', $brand["content"], $media);
        }
        if (!empty($media[1][0])) {
            if (strpos($media[1][0], $json->hostname))
                $media[1][0] = str_replace("http://" . $json->hostname, "", $media[1][0]);
            if ((strpos($media[1][0], "http:") === false) && (strpos($media[1][0], "https:") === false)) {
                $fileName = trim(str_replace("/images/$lang/$section", "", $media[1][0]), "/");
                if (!in_array($fileName, $usedImages))
                    $usedImages[] = $fileName;
            }
        }
    }
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