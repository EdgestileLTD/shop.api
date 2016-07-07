<?php

function getImages($id)
{

    global $json;

    $u = new seTable('news_img', 'ni');
    $u->where('ni.id_news=?', $id);
    $objects = $u->getList();
    $result = array();
    foreach ($objects as $item) {
        $image = null;
        $image['id'] = $item['id'];
        $image['imageFile'] = $item['picture'];
        $image['imageAlt'] = $item['picture_alt'];
        $image['sortIndex'] = $item['sort'];
        $image['isMain'] = (bool)$item['default'];
        if ($image['imageFile']) {
            if (strpos($image['imageFile'], "://") === false) {
                $image['imageUrl'] = 'http://' . $json->hostname . "/images/rus/newsimg/" . $image['imageFile'];
                $image['imageUrlPreview'] = "http://{$json->hostname}/lib/image.php?size=64&img=images/rus/newsimg/" . $image['imageFile'];
            } else {
                $image['imageUrl'] = $image['imageFile'];
                $image['imageUrlPreview'] = $image['imageFile'];
            }
        }
        $result[] = $image;
    }
    return $result;
}

if (empty($json->ids))
    $json->ids[] = $_GET['id'];
$ids = implode(",", $json->ids);

$u = new seTable('news', 'n');
$u->select('n.*, nc.title AS nameGroup');
$u->leftjoin('news_category nc', 'n.id_category = nc.id');
$u->where('n.id in (?)', $ids);
$result = $u->getList();

$items = array();
foreach ($result as $item) {
    $new = null;
    $new['id'] = $item['id'];
    $new['idGroup'] = $item['id_category'];
    $new['nameGroup'] = $item['nameGroup'];
    $new['name'] = $item['title'];
    $new['isActive'] = $item['active'] == 'Y';
    $new['imageFile'] = $item['img'];
    $new['description'] = $item['short_txt'];
    $new['fullDescription'] = $item['text'];
    if (!empty($item['news_date']))
        $new['newsDate'] = date('Y-m-d', $item['news_date']);
    if (!empty($item['pub_date']))
        $new['publicationDate'] = date('Y-m-d', $item['pub_date']);
    $new['images'] = getImages($item['id']);
    if ($new['imageFile']) {
        if (strpos($new['imageFile'], "://") === false) {
            $new['imageUrl'] = 'http://' . $json->hostname . "/images/rus/newsimg/" . $new['imageFile'];
            $new['imageUrlPreview'] = "http://{$json->hostname}/lib/image.php?size=64&img=images/rus/newsimg/" . $new['imageFile'];
        } else {
            $new['imageUrl'] = $new['imageFile'];
            $new['imageUrlPreview'] = $new['imageFile'];
        }
    }
    $items[] = $new;
}

$data['count'] = sizeof($items);
$data['items'] = $items;

if (se_db_error()) {
    $status['status'] = 'error';
    $status['errortext'] = 'Не удаётся получить информация о новсти!';
} else {
    $status['status'] = 'ok';
    $status['data'] = $data;
}

outputData($status);


