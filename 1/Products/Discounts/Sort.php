<?php

$sortIndexes = $json->sortIndexes;

$u = new seTable('shop_discounts','sd');
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
    $status['errortext'] = 'Не удаётся произвести сортировку!';
}

outputData($status);