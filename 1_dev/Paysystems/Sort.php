<?php
    $sortIndexes = $json->sortIndexes;

    $u = new seTable('shop_payment','sp');
    foreach($sortIndexes as $index) {
        $u->select('id, sort');
        if ($u->find($index->id)) {
            $u->sort = $index->index;
            $u->save();
        }
    }

    $status = array();
    if (!mysql_error())
        $status['status'] = 'ok';
    else {
        $status['status'] = 'error';
        $status['errortext'] = mysql_error();
    }

    outputData($status);