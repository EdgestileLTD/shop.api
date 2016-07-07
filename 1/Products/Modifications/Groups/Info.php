<?php

    if (empty($json->ids))
        exit;

    $ids = implode(",", $json->ids);

    $newTypes = array("string" => "S", "number" => "D", "bool" => "B", "list" => "L", "colorlist" => "CL");

    $u = new seTable('shop_modifications_group','smg');
    $u->select('smg.*, GROUP_CONCAT(DISTINCT(CONCAT_WS("\t", sf.id, sgf.id, sf.name, sf.type)) SEPARATOR "\n") AS `values`, COUNT(DISTINCT(sm.id_price)) countGoods');
    $u->leftjoin('shop_group_feature sgf', 'smg.id=sgf.id_group');
    $u->leftjoin('shop_feature sf', 'sf.id=sgf.id_feature');
    $u->leftjoin('shop_modifications sm', 'sm.id_mod_group=smg.id');
    $u->where("smg.id in ($ids)");

    $objects = $u->getList();

    foreach ($objects as $item) {
        $group = null;
        $group['id'] = $item['id'];
        $group['name'] = $item['name'];
        $group['type'] = $item['vtype'];
        $group['countGoods'] = $item['countGoods'];
        if (empty($group['type']))
            $group['type'] = "0";
        $group['sortIndex'] = (int)$item['sort'];
        $values = null;
        if (!empty($item['values'])) {
            $params = explode("\n", $item['values']);
            foreach ($params as $itemParam) {
                $itemParam = explode("\t", $itemParam);
                $value = array();
                $value['id'] = $itemParam[0];
                $value['idGroup'] = $itemParam[1];
                $value['name'] = $itemParam[2];
                $value['type'] = $itemParam[3];
                $value['valueType'] = $newTypes[$value['type']];
                $values[] = $value;
            }
        }
        $group['columns'] = $values;
        $items[] = $group;
    }

    $data['items'] = $items;

    if (se_db_error()) {
        $status['status'] = 'error';
        $status['errortext'] = 'Не удаётся получить информацию о типе товара!';
    } else {
        $status['status'] = 'ok';
        $status['data'] = $data;
    }

    outputData($status);