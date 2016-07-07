<?php

$ids = array();
if (empty($json->ids) && !empty($json->id))
    $ids[] = $json->id;
else $ids = $json->ids;
$isNew = empty($ids);
if (!$isNew)
    $idsStr = implode(",", $ids);

function getSortIndex()
{
    $u = new seTable('shop_section_item','ssi');
    $u->select('MAX(sort) AS sort');
    $u->fetchOne();
    return $u->sort + 1;
}

$u = new seTable('shop_section_item', 'ssi');

if ($isNew || !empty($ids)) {
    $isUpdated = false;
    if ($isNew)
        $json->sortIndex = getSortIndex();
    $isUpdated |= setField($isNew, $u, $json->idGroup, 'id_section');
    $isUpdated |= setField($isNew, $u, $json->sortIndex, 'sort');
    $isUpdated |= setField($isNew, $u, $json->name, 'name');
    $isUpdated |= setField($isNew, $u, $json->note, 'note');
    $isUpdated |= setField($isNew, $u, $json->isActive, 'enabled');
    $isUpdated |= setField($isNew, $u, $json->imageFile, 'picture');
    $isUpdated |= setField($isNew, $u, $json->imageAlt, 'picture_alt');
    $isUpdated |= setField($isNew, $u, $json->url, 'url');
    setField($isNew, $u, null, 'id_price');
    setField($isNew, $u, null, 'id_group');
    setField($isNew, $u, null, 'id_brand');
    setField($isNew, $u, null, 'id_new');
    if (!empty($json->value)) {
        if ($json->value == "productGroup")
            $isUpdated |= setField($isNew, $u, $json->idValue, 'id_group');
        if ($json->value == "product")
            $isUpdated |= setField($isNew, $u, $json->idValue, 'id_price');
        if ($json->value == "brand")
            $isUpdated |= setField($isNew, $u, $json->idValue, 'id_brand');
        if ($json->value == "publication")
            $isUpdated |= setField($isNew, $u, $json->idValue, 'id_new');
    }

    if ($isUpdated){
        if (!empty($idsStr)) {
            if ($idsStr != "all")
                $u->where('id in (?)', $idsStr);
            else $u->where('true');
        }
        $idv = $u->save();
        if ($isNew)
            $ids[] = $idv;
    }
}

$data['id'] = $ids[0];
$status = array();
if (!se_db_error()) {
    $status['status'] = 'ok';
    $status['data'] = $data;
} else {
    $status['status'] = 'error';
    $status['errortext'] = 'Не удаётся сохранить информацию об элементе раздела!';
}

outputData($status);