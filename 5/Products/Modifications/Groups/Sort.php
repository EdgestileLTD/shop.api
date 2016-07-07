<?php
    $indexes = getRequest('indexes', 3);

    $ids = explode(',', $ids);
    $indexes = explode(',', $indexes);

    $u = new seTable('shop_modifications_group','smg');
    for ($i = 0; $i < sizeof($ids); $i++) {
        $u->select('id, sort');
        $u->find($ids[$i]);
        $u->sort = $indexes[$i];
        $u->save();
    }

    $status = array();
    if (!se_db_error())
        $status['status'] = 'ok';
    else {
        $status['status'] = 'error';
        $status['errortext'] = se_db_error();
    }

    outputData($status);