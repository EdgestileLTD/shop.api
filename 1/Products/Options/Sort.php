<?php
/**
 * Created by PhpStorm.
 * User: developer
 * Date: 10.06.2017
 * Time: 19:19
 */

$sortIndexes = $json->sortIndexes;

$u = new seTable('shop_option','so');
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