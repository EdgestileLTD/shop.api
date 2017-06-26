<?php
/**
 * Created by PhpStorm.
 * User: developer
 * Date: 10.06.2017
 * Time: 12:11
 */

function getSortIndex()
{
    $u = new seTable('shop_option_group','sog');
    $u->select('MAX(sog.sort) AS sort');
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

$u = new seTable('shop_option_group','sog');

if ($isNew  || !empty($ids)) {
    $isUpdated = false;
    if ($isNew)
        $json->sortIndex = getSortIndex();

    $isUpdated |= setField($isNew, $u, $json->name, 'name');
    $isUpdated |= setField($isNew, $u, $json->sortIndex, 'sort');
    $isUpdated |= setField($isNew, $u, $json->isActive, 'is_active');
    $isUpdated |= setField($isNew, $u, $json->description, 'description');

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