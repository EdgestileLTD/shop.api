<?php
    $itemsSEO = $json->itemsSEO;

    $u = new seTable('shop_feature','sf');
    foreach($itemsSEO as $item) {
        $u->select('id, seo');
        if ($u->find($item->id)) {
            $u->seo = $item->isSEO;
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