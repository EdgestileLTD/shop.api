<?php

require_once "functions.php";

if ($json->ids) {
    $ids = implode(",", $json->ids);

    $u = new seTable('shop_order_payee', 'sop');
    $u->select('id_order, id_user_account_in, id_user_account_out');
    $u->where('id in (?)', $ids);
    $idsOrders = $u->getList();

    $u = new seTable('shop_order_payee', 'sop');
    $u->where('id in (?)', $ids)->deletelist();

    $idsAccounts = array();
    foreach ($idsOrders as $item) {
        checkStatusOrder($item['id_order']);
        if (!empty($item['id_user_account_in']))
            $idsAccounts[] = $item['id_user_account_in'];
        if (!empty($item['id_user_account_out']))
            $idsAccounts[] = $item['id_user_account_out'];
    }
    if (!empty($idsAccounts)) {
        $idsAccounts = implode(',', $idsAccounts);
        $u = new seTable('se_user_account', 'sua');
        $u->where('id in (?)', $idsAccounts)->deletelist();
    }
}

$status = array();
if (!mysql_error()) {
    $status['status'] = 'ok';
} else {
    $status['status'] = 'error';
    $status['error'] = mysql_error();
}

outputData($status);