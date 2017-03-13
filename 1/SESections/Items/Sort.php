<?php
$sortIndexes = $json->sortIndexes;

$u = new seTable('shop_section_item','ssi');
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
    $status['error'] = 'Не удается произвести сортировку элемента!';
}

outputData($status);