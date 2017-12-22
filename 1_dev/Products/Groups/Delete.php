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
    if (CORE_VERSION != "5.2") {
        $u = new seTable('shop_price_group', 'spg');
        $u->where('id_group in (?)', $ids)->deletelist();
        $u = new seTable('shop_group_tree', 'sgt');
        $u->select('sgt.id_child id');
        $u->where("sgt.id_parent IN ({$ids})");
        $result = $u->getList();
        $ids = array();
        foreach ($result as $item)
            $ids[] = $item['id'];
        $ids = implode(",", $ids);
        if (empty($ids))
            $ids = implode(",", $json->ids);
        $u = new seTable('shop_group_tree', 'sgt');
        $u->where('id_parent in (?)', $ids)->deletelist();
        $u = new seTable('shop_group', 'sg');
        $u->where('id in (?)', $ids)->deletelist();
    } else
        deleteGroups($json->ids);
}

$status = array();
if (!se_db_error()) {
    $status['status'] = 'ok';
} else {
    $status['status'] = 'error';
    $status['error'] = 'Не удаётся удалить группу товаров!';
}

outputData($status);



