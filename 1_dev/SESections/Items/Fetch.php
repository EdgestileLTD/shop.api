<?php

$u = new seTable('shop_section_item', 'ssi');
$u->select('ssi.*, sb.image imageBrand, sg.picture imageGroup, si.picture imageProduct, n.img imageNews');
$u->leftjoin('shop_brand sb', 'sb.id = ssi.id_brand');
$u->leftjoin('shop_group sg', 'sg.id = ssi.id_group');
$u->leftjoin('shop_img si', 'si.id_price = ssi.id_price');
$u->leftjoin('news n', 'n.id = ssi.id_new');
if (!empty($json->idParent))
    $u->where('id_section=?', $json->idParent);
$u->orderby("ssi.sort");
$u->groupby('ssi.id');
$rowsSectionsItems = $u->getList();

$items = array();
foreach ($rowsSectionsItems as $sectionItem) {
    $item = null;
    $item['id'] = $sectionItem['id'];
    $item['idGroup'] = $sectionItem['id_section'];
    $item['name'] = $sectionItem['name'];
    $item['note'] = $sectionItem['note'];
    $item['url'] = $sectionItem['url'];
    $item['imageFile'] = $sectionItem['picture'];
    $item['imageAlt'] = $sectionItem['picture_alt'];
    $item['sortIndex'] = (int) $sectionItem['sort'];
    $item['isActive'] = (bool) $sectionItem['enabled'];
    $item['description'] = 'Ссылка';
    if ($item['imageFile']) {
        if (strpos($item['imageFile'], "://") === false) {
            $item['imageUrl'] = 'http://' . $json->hostname . "/images/rus/shopsections/" . $item['imageFile'];
            $item['imageUrlPreview'] = "http://{$json->hostname}/lib/image.php?size=64&img=images/rus/shopsections/" . $item['imageFile'];
        } else {
            $item['imageUrl'] = $item['imageFile'];
            $item['imageUrlPreview'] = $item['imageFile'];
        }
    }
    
    if (!empty($sectionItem['id_price'])) {
        $item['idValue'] = $sectionItem['id_price'];
        $item['value'] = 'product';
        $item['description'] = 'Товар';
        if (empty($item['imageFile'])) {
            $item['imageUrl'] = strpos($sectionItem['imageProduct'], "http") === false ?
                'http://' . $json->hostname . "/images/rus/shopprice/" . $sectionItem['imageProduct'] :
                $sectionItem['imageProduct'];
            $item['imageUrlPreview'] = strpos($sectionItem['imageProduct'], "http") === false ?
                "http://{$json->hostname}/lib/image.php?size=64&img=images/rus/shopprice/" .
                $sectionItem['imageProduct'] : $sectionItem['imageProduct'];
        }
    } elseif (!empty($sectionItem['id_group'])) {
        $item['idValue'] = $sectionItem['id_group'];
        $item['value'] = 'productGroup';
        $item['description'] = 'Категория товара';
        if (empty($item['imageFile'])) {
            $item['imageUrl'] = strpos($sectionItem['imageGroup'], "http") === false ?
                'http://' . $json->hostname . "/images/rus/shopgroup/" . $sectionItem['imageGroup'] :
                $sectionItem['imageGroup'];
            $item['imageUrlPreview'] = strpos($sectionItem['imageGroup'], "http") === false ?
                "http://{$json->hostname}/lib/image.php?size=64&img=images/rus/shopgroup/" .
                $sectionItem['imageGroup'] : $sectionItem['imageGroup'];
        }

    } elseif (!empty($sectionItem['id_brand'])) {
        $item['idValue'] = $sectionItem['id_brand'];
        $item['value'] = 'brand';
        $item['description'] = 'Бренд';
        if (empty($item['imageFile'])) {
            $item['imageUrl'] = strpos($sectionItem['imageBrand'], "http") === false ?
                'http://' . $json->hostname . "/images/rus/shopbrand/" . $sectionItem['imageBrand'] :
                $sectionItem['imageBrand'];
            $item['imageUrlPreview'] = strpos($sectionItem['imageBrand'], "http") === false ?
                "http://{$json->hostname}/lib/image.php?size=64&img=images/rus/shopbrand/" .
                $sectionItem['imageBrand'] : $sectionItem['imageBrand'];
        }
    } elseif (!empty($sectionItem['id_new'])) {
        $item['idValue'] = $sectionItem['id_new'];
        $item['value'] = 'publication';
        $item['description'] = 'Новость';
        if (empty($item['imageFile'])) {
            $item['imageUrl'] = strpos($sectionItem['imageNews'], "http") === false ?
                'http://' . $json->hostname . "/images/rus/newsimg/" . $sectionItem['imageNews'] :
                $sectionItem['imageNews'];
            $item['imageUrlPreview'] = strpos($sectionItem['imageNews'], "http") === false ?
                "http://{$json->hostname}/lib/image.php?size=64&img=images/rus/newsimg/" .
                $sectionItem['imageNews'] : $sectionItem['imageNews'];
        }
    }
    $items[] = $item;
}

$data['count'] = count($items);
$data['items'] = $items;

$status = array();
if (!se_db_error()) {
    $status['status'] = 'ok';
    $status['data'] = $data;
} else {
    $status['status'] = 'error';
    $status['errortext'] = 'Не удаётся прочитать элементы раздела!';
}

outputData($status);