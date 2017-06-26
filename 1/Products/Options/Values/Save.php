<?php
/**
 * Created by PhpStorm.
 * User: developer
 * Date: 10.06.2017
 * Time: 19:19
 */

function getSortIndex()
{
    $u = new seTable('shop_option_value','sov');
    $u->select('MAX(sov.sort) AS sort');
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

$u = new seTable('shop_option_value','sov');

if ($isNew  || !empty($ids)) {
    $isUpdated = false;
    if ($isNew)
        $json->sortIndex = getSortIndex();

    $isUpdated |= setField($isNew, $u, $json->name, 'name');
    $isUpdated |= setField($isNew, $u, $json->price, 'price');
    $isUpdated |= setField($isNew, $u, $json->sortIndex, 'sort');
    $isUpdated |= setField($isNew, $u, $json->imageFile, 'image');
    $isUpdated |= setField($isNew, $u, $json->isActive, 'is_active');

    if (isset($json->idOption))
        $isUpdated |= setField($isNew, $u, $json->idOption, 'id_option');

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