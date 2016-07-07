<?php
$newTypes = array("S" => "string", "D" => "number", "B" => "bool", "L" => "list", "CL" => "colorlist");

function getSortIndex()
{
    $u = new seTable('shop_feature', 'sf');
    $u->select('MAX(sf.sort) AS sort');
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

$u = new seTable('shop_feature', 'sf');

if ($isNew || !empty($ids)) {
    $isUpdated = false;
    if ($isNew)
        $json->sortIndex = getSortIndex();

    if (!empty($json->valueType))
        $json->type = $newTypes[$json->valueType];

    $isUpdated |= setField($isNew, $u, $json->idGroup, 'id_feature_group');
    $isUpdated |= setField($isNew, $u, $json->type, 'type');
    $isUpdated |= setField($isNew, $u, $json->measure, 'measure');
    $isUpdated |= setField($isNew, $u, $json->name, 'name');
    $isUpdated |= setField($isNew, $u, $json->sortIndex, 'sort');
    $isUpdated |= setField($isNew, $u, $json->imageFile, 'image');
    $isUpdated |= setField($isNew, $u, $json->description, 'description');
    $isUpdated |= setField($isNew, $u, $json->isYAMarket, 'is_market');

    if ($isUpdated) {
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
    $status['errortext'] = 'Не удаётся сохранить информацию о параметре!';
}

outputData($status);

