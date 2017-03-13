<?php
    $sortIndexes = $json->sortIndexes;

    $u = new seTable('shop_userfield_groups','sug');
    foreach($sortIndexes as $index) {
        $u->select('id, sort');
        if ($u->find($index->id)) {
            $u->sort = $index->index;
            $u->save();
        }
    }

    $status = array();
    if (!se_db_error())
        $status['status'] = 'ok';
    else {
        $status['status'] = 'error';
        $status['error'] = se_db_error();
    }

    outputData($status);