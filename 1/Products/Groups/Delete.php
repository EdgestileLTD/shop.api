<?php

function deleteGroups($ids)
{
    $ids = implode(",", $ids);

    $u = new seTable('shop_group', 'sg');
    $u->select('id');
    $u->where('upid IN (?)', $ids);
    $items = $u->getList();
    $idsChild = array();
    foreach ($items as $item)
        $idsChild[] = $item['id'];
    if ($idsChild)
        deleteGroups($idsChild);

    $u = new seTable('shop_price', 'sp');
    $u->where('id_group IN (?)', $ids)->deletelist();
    $u = new seTable('shop_group', 'sg');
    $u->where('id IN (?)', $ids)->deletelist();
}

if ($json->ids) {
    $ids = implode(",", $json->ids);
    if (CORE_VERSION == "5.3") {
        se_db_query("SET AUTOCOMMIT=0; START TRANSACTION");
        $u = new seTable('shop_price_group', 'spg');
        $u->where('id_group in (?)', $ids)->deletelist();
        $u = new seTable('shop_group_tree', 'sgt');
        $u->select('sgt.id_child id');
        $u->where("sgt.id_parent IN ({$ids})");
        $result = $u->getList();
        $idsChilds = array();
        foreach ($result as $item)
            $idsChilds[] = $item['id'];
        $idsChilds = implode(",", $ids);
        if ($idsChilds) {
            $u = new seTable('shop_group_tree', 'sgt');
            $u->where('id_parent in (?)', $idsChilds)->deletelist();
            $u = new seTable('shop_group', 'sg');
            $u->where('id in (?)', $idsChilds)->deletelist();
        }
        $u = new seTable('shop_group_tree', 'sgt');
        $u->where('id_parent in (?)', $ids)->deletelist();
        $u = new seTable('shop_group', 'sg');
        $u->where('id in (?)', $ids)->deletelist();
        if (!se_db_error())
            se_db_query("COMMIT");
        else se_db_query("ROLLBACK");
    } else
        deleteGroups($json->ids);
}

$status = array();
if (!se_db_error()) {
    $status['status'] = 'ok';
} else {
    $status['status'] = 'error';
    $status['errortext'] = 'Не удаётся удалить группу товаров!';
}

outputData($status);



