<?php

$ids = array();
if (empty($json->ids) && !empty($json->id))
    $ids[] = $json->id;
else $ids = $json->ids;
$isNew = empty($ids);
if (!$isNew)
    $idsStr = implode(",", $ids);

function saveFeatures($ids, $features) {
    $idsStr = implode(",", $ids);
    $idsFeatures = array();
    foreach ($features as $feature)
        $idsFeatures[] = $feature->id;

    $u = new seTable('shop_product_type_feature', 'sptf');
    $u->select("id_feature");
    $u->where('id_type IN (?)', $idsStr);
    $u->andWhere('id_feature IN (?)', implode(",", $idsFeatures));
    $result = $u->getList();
    $idsFeatures = array();
    foreach ($result as $item)
        $idsFeatures[] = $item["id_feature"];

    $u = new seTable('shop_product_type_feature', 'sptf');
    $u->where('id_type IN (?)', $idsStr);
    $u->andWhere('NOT id_feature IN (?)', implode(",", $idsFeatures));
    $u->deleteList();

    foreach ($ids as $id)
        foreach ($features as $feature)
            if (!in_array($feature->id, $idsFeatures))
                $data[] = array('id_type' => $id, 'id_feature' => $feature->id);
    if (!empty($data))
        se_db_InsertList('shop_product_type_feature', $data);
}

$u = new seTable('shop_product_type', 'spt');

if ($isNew || !empty($ids)) {
    $isUpdated = false;
    $isUpdated |= setField($isNew, $u, $json->name, 'name');

    if ($isUpdated) {
        if (!empty($idsStr)) {
            if ($idsStr != "all")
                $u->where('id in (?)', $idsStr);
        }
        $idv = $u->save();
        if ($isNew)
            $ids[] = $idv;
    }

    if ($ids && isset($json->featuresList))
        saveFeatures($ids, $json->featuresList);
}

$data['id'] = $ids[0];
$status = array();
if (!se_db_error()) {
    $status['status'] = 'ok';
    $status['data'] = $data;
} else {
    $status['status'] = 'error';
    $status['error'] = 'Не удаётся сохранить информация о типе товара!';
}

outputData($status);
