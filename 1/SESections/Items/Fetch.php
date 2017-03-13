<?php

$u = new seTable('shop_section_item', 'ssi');
$u->select('ssi.*, sb.image imageBrand');
$u->leftjoin('shop_brand sb', 'sb.id = ssi.id_brand');
if (!empty($json->idParent))
    $u->where('id_section=?', $json->idParent);
$u->orderby("sort");
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
    if (!empty($sectionItem['id_price'])) {
        $item['idValue'] = $sectionItem['id_price'];
        $item['value'] = 'product';
        $item['description'] = 'Товар';
        if (empty($item['imageFile'])) {
            $item['imageUrl'] = strpos($sectionItem['imageProduct'], "http") === false ?
                "http://{$json->hostname}/lib/image.php?size=64&img=images/rus/shopprice/" .
                $sectionItem['imageProduct'] : $sectionItem['imageProduct'];
        }
    } elseif (!empty($sectionItem['id_group'])) {
        $item['idValue'] = $sectionItem['id_group'];
        $item['value'] = 'productGroup';
        $item['description'] = 'Категория товара';
    } elseif (!empty($sectionItem['id_brand'])) {
        $item['idValue'] = $sectionItem['id_brand'];
        $item['value'] = 'brand';
        $item['description'] = 'Бренд';
        if (empty($item['imageFile'])) {
            $item['imageUrl'] = strpos($sectionItem['imageBrand'], "http") === false ?
                "http://{$json->hostname}/lib/image.php?size=64&img=images/rus/shopbrand/" .
                $sectionItem['imageBrand'] : $sectionItem['imageBrand'];
        }
    } elseif (!empty($sectionItem['id_new'])) {
        $item['idValue'] = $sectionItem['id_new'];
        $item['value'] = 'publication';
        $item['description'] = 'Новость';
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
    $status['error'] = 'Не удаётся прочитать элементы раздела!';
}

outputData($status);