<?php
/**
 * Created by PhpStorm.
 * User: developer
 * Date: 10.06.2017
 * Time: 19:18
 */

function getSortIndex()
{
    $u = new seTable('shop_option','so');
    $u->select('MAX(so.sort) AS sort');
    $u->fetchOne();
    return $u->sort + 1;
}

$ids = array();
if (empty($json->ids) && !empty($json->id))
    $ids[] = $json->id;
else $ids = $json->ids;
$isNew = empty($ids);
if (!$isNew)
    $idsStr = implode(",", $ids);

$u = new seTable('shop_option','so');

if ($isNew  || !empty($ids)) {
    $isUpdated = false;
    if ($isNew)
        $json->sortIndex = getSortIndex();

    $isUpdated |= setField($isNew, $u, $json->name, 'name');
    $isUpdated |= setField($isNew, $u, $json->code, 'code');
    $isUpdated |= setField($isNew, $u, $json->note, 'note');
    $isUpdated |= setField($isNew, $u, $json->imageFile, 'image');
    $isUpdated |= setField($isNew, $u, $json->type, 'type');
    $isUpdated |= setField($isNew, $u, $json->typePrice, 'type_price');
    $isUpdated |= setField($isNew, $u, $json->isCounted, 'is_counted');
    $isUpdated |= setField($isNew, $u, $json->sortIndex, 'sort');
    $isUpdated |= setField($isNew, $u, $json->isActive, 'is_active');
    $isUpdated |= setField($isNew, $u, $json->description, 'description');
    if (isset($json->idGroup))
        $isUpdated |= setField($isNew, $u, $json->idGroup, 'id_group');

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
};

$data['id'] = $ids[0];
$status = array();
if (!se_db_error()) {
    $status['status'] = 'ok';
    $status['data'] = $data;
} else {
    $status['status'] = 'error';
    $status['error'] = se_db_error();
}

outputData($status);