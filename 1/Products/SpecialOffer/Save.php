<?php
    $ids = array();
    if (empty($json->ids) && !empty($json->id))
        $ids[] = $json->id;
    else $ids = $json->ids;
    $idsStr = implode(",", $ids);

    foreach ($ids as $id)
        $data[] = array('id_price' => $id);

    if (!empty($data))
        se_db_InsertList('shop_leader', $data);

    $data['id'] = $ids[0];
    $status = array();
    if (!se_db_error()) {
        $status['status'] = 'ok';
        $status['data'] = $data;
    } else {
        $status['status'] = 'error';
        $status['error'] = se_db_error();
    }

    outputData($status);


