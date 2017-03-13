<?php

function getSortIndex() {
    $s = new seTable('shop_modifications_group','smg');
    $s->select('MAX(sort) AS sortindex');
    $s->fetchOne();
    return $s->sortindex + 1;
}

function saveFeatures($idsGroups, $features) {

    $idsGroupsStr = implode(",", $idsGroups);
    $u = new seTable('shop_group_feature','sgf');
    $u->where('id_group IN (?)', $idsGroupsStr)->deleteList();

    if ($features) {
        foreach($idsGroups as $idGroup) {
            foreach($features as $feature)
                $data[] = array('id_group' => $idGroup, 'id_feature' => $feature->id);
        }
        if (!empty($data))
            se_db_InsertList('shop_group_feature', $data);
    }
}

$ids = array();
if (empty($json->ids) && !empty($json->id))
    $ids[] = $json->id;
else $ids = $json->ids;
$isNew = empty($ids);
if (!$isNew)
    $idsStr = implode(",", $ids);

$u = new seTable('shop_modifications_group','smg');

if ($isNew  || !empty($ids)) {
    $isUpdated = false;
    if ($isNew)
        $json->sortIndex = getSortIndex();

    if (isset($json->type))
        settype($json->type, "integer");

    $isUpdated |= setField($isNew, $u, $json->name, 'name');
    $isUpdated |= setField($isNew, $u, $json->type, 'vtype');
    $isUpdated |= setField($isNew, $u, $json->sortIndex, 'sort');

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

    if ($ids && isset($json->columns))
        saveFeatures($ids, $json->columns);
};

$data['id'] = $ids[0];
$status = array();
if (!se_db_error()) {
    $status['status'] = 'ok';
    $status['data'] = $data;
} else {
    $status['status'] = 'error';
    $status['error'] = 'Не удаётся сохранить параметр товара!';
}

outputData($status);