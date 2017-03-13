<?php

require_once "functions.php";

function saveOrderAccount()
{
    GLOBAL $json;

    if ($json->idUserAccountOut > 0) {
        $u = new seTable('se_user_account', 'sua');
        $u->where('id = ?', $json->idUserAccountOut)->deletelist();
    }
    if ($json->idUserAccountIn > 0) {
        $u = new seTable('se_user_account', 'sua');
        $u->where('id = ?', $json->idUserAccountIn)->deletelist();
    }

    if ($json->paymentTarget === 1 || $json->idPaymentType > 0) {
        $u = new seTable('se_user_account', 'sua');
        setField(1, $u, $json->idPayer, 'user_id');
        setField(1, $u, date("Y-m-d"), 'date_payee');
        setField(1, $u, 1, 'operation');
        setField(1, $u, $json->amount, 'in_payee');
        $document = null;
        if ($json->paymentTarget === 1)
            $document = 'Поступление средств на счёт';
        else $document = 'Поступление наличных в счёт заказа № ' . $json->idOrder;
        setField(1, $u, $document, 'docum');
        $json->idUserAccountIn = $u->save();
    } else $json->idUserAccountIn = null;

    if ($json->paymentTarget === 0) {
        $u = new seTable('se_user_account', 'sua');
        setField(1, $u, $json->idPayer, 'user_id');
        setField(1, $u, date("Y-m-d"), 'date_payee');
        setField(1, $u, 2, 'operation');
        setField(1, $u, $json->orderAmount, 'out_payee');
        $document = 'Оплата заказа № ' . $json->idOrder;
        setField(1, $u, $document, 'docum');
        $json->idUserAccountOut = $u->save();
    } else $json->idUserAccountOut = 0;
}

function getDocNum()
{
    $u = new seTable('shop_order_payee', 'sop');
    $u->select('MAX(num) num');
    $u->where('sop.`year` = YEAR(CURDATE())');
    $u->fetchOne();
    return $u->num + 1;
}

$ids = array();
if (empty($json->ids) && !empty($json->id))
    $ids[] = $json->id;
else $ids = $json->ids;
$isNew = empty($ids);

if (!$isNew)
    $idsStr = implode(",", $ids);

if ($isNew || !empty($ids)) {

    saveOrderAccount();

    $u = new seTable('shop_order_payee');
    if ($isNew) {
        $u->id = $ids[0];
        if (empty($json->docNum))
            $u->num = getDocNum();
        if (empty($json->docDate))
            $u->date = date("Y-m-d");
    }

    if (empty($json->year)) {
        $json->year = date('Y', strtotime($json->orderDate));
    }

    $isUpdated = false;
    if (empty($json->idOrder))
        se_db_query('ALTER TABLE shop_order_payee CHANGE COLUMN id_order id_order INT(10) UNSIGNED DEFAULT NULL');
    $isUpdated |= setField($isNew, $u, $json->idOrder, 'id_order');
    $isUpdated |= setField($isNew, $u, $json->idPayer, 'id_author');
    $isUpdated |= setField($isNew, $u, $json->docYear, 'year', "smallint(6) unsigned NOT NULL DEFAULT '2000'", 1);
    if ($json->docDate)
        $json->docDate = date('Y-m-d', strtotime($json->docDate));
    $isUpdated |= setField($isNew, $u, $json->docDate, 'date');
    $isUpdated |= setField($isNew, $u, $json->paymentTarget, 'payment_target',
        'smallint(6) UNSIGNED DEFAULT 0 COMMENT "Цель платежа: 0 - заказ, 1 - пополнение счёта"', 1);
    $isUpdated |= setField($isNew, $u, $json->idPaymentType, 'payment_type', 'int(10) default 1', 1);
    $isUpdated |= setField($isNew, $u, $json->idManager, 'id_manager', 'int(10) default 0', 1);
    $isUpdated |= setField($isNew, $u, $json->amount, 'amount', 'decimal(10,2) default 0.00');
    $isUpdated |= setField($isNew, $u, $json->currency, 'curr', 'char(3) DEFAULT "RUR" COMMENT "Код валюты платежа"');
    $isUpdated |= setField($isNew, $u, $json->note, 'note', "varchar(255) default ''");
    $isUpdated |= setField($isNew, $u, $json->idUserAccountIn, 'id_user_account_in', 'int(10) default NULL', 1);
    $isUpdated |= setField($isNew, $u, $json->idUserAccountOut, 'id_user_account_out', 'int(10) default NULL', 1);

    if ($isUpdated) {
        if (!empty($idsStr))
            $u->where('id in (?)', $idsStr);
        $idv = $u->save();
        if ($isNew)
            $ids[] = $idv;
    }

    if ($ids && !empty($json->idOrder))
        checkStatusOrder($json->idOrder);
}

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
