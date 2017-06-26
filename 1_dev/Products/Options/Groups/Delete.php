<?php
/**
 * Created by PhpStorm.
 * User: developer
 * Date: 10.06.2017
 * Time: 12:12
 */

if ($json->ids) {
    $ids = implode(",", $json->ids);
    $u = new seTable('shop_option_group', 'sog');
    $u->where('id in (?)', $ids)->deletelist();
}

$status = array();
if (!se_db_error()) {
    $status['status'] = 'ok';
} else {
    $status['status'] = 'error';
    $status['error'] = se_db_error();
}

outputData($status);