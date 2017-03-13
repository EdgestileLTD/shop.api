<?php
    $ids = array();
    if (empty($json->ids) && !empty($json->id))
        $ids[] = $json->id;
    else $ids = $json->ids;
    $idsStr = implode(",", $ids);

    if ($ids) {
        $u = new seTable('shop_price','sp');
        if ($json->value == "a")
            $u->update('price', "price+" . $json->price);
        if ($json->value == "p")
            $u->update('price', "price+price*" . $json->price/100);
        if (strpos($idsStr, "*") === false)
            $u->where('id IN (?)', $idsStr);
        else $u->where('TRUE');
        $u->save();
    }

    $status = array();
    if (!se_db_error()) {
        $status['status'] = 'ok';
        $status['data'] = $ids;
    } else {
        $status['status'] = 'error';
        $status['error'] = se_db_error();
    }

    outputData($status);