<?php

    $u = new seTable('se_account_operation', 'sao');
    $u->select('sao.*');
    $result = $u->getList();

    foreach($result as $item) {
        $operation = null;
        $operation['id'] = $item['operation_id'];
        $operation['name'] = $item['name'];
        $items[] = $operation;
    }

    $data['count'] = sizeof($items);
    $data['items'] = $items;

    $status = array();
    if (!se_db_error()) {
        $status['status'] = 'ok';
        $status['data'] = $data;
    } else {
        $status['status'] = 'error';
        $status['error'] = se_db_error();
    }

    outputData($status);