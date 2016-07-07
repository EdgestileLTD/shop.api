<?php
    if (empty($json->ids))
        $json->ids[] = $_GET['id'];
    $ids = implode(",", $json->ids);

    $u = new seTable('shop_feature_group','sfg');
    $u->where('sfg.id in (?)', $ids);
    $result = $u->getList();

    $items = array();
    foreach($result as $item) {
        $group = null;
        $group['id'] = $item['id'];
        $group['name'] = $item['name'];
        $group['description'] = $item['description'];
        $group['imageFile'] = $item['image'];
        $group['sortIndex'] = (int) $item['sort'];
        $items[] = $group;
    }
    
    $data['count'] = sizeof($items);
    $data['items'] = $items;
    
    if (se_db_error()) {
        $status['status'] = 'error';
        $status['errortext'] = se_db_error();
    } else {
        $status['status'] = 'ok';
        $status['data'] = $data;
    }
    
    outputData($status);
