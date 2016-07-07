<?php
    $sortIndexes = $json->sortIndexes;

    $u = new seTable('shop_group','sg');
    foreach($sortIndexes as $index) {
        $u->select('id, position');
        if ($u->find($index->id)) {
            $u->position = $index->index;
            $u->save();
        }
    }

    $status = array();
    if (!se_db_error())
        $status['status'] = 'ok';
    else {
        $status['status'] = 'error';
        $status['errortext'] = se_db_error();
    }

    outputData($status);