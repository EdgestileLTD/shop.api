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
        foreach ($json->ids as $id) {
            se_db_query("DELETE FROM shop_price sp INNER JOIN shop_price_group spg ON sp.id = spg.id_price WHERE spg.id_group = {$id}");
            $u = new seTable('shop_group_tree', 'sgt');
            $u->where("id_parent = ?", $id)->deletelist();
            $u = new seTable('shop_group', 'sg');
            $u->where('id = ?', $id)->deletelist();
        }
    } else
        deleteGroups($json->ids);
}

$status = array();
if (!se_db_error()) {
    $status['status'] = 'ok';
} else {
    $status['status'] = 'error';
    $status['error'] =  'Не удаётся удалить группу товаров!';
}

outputData($status);



