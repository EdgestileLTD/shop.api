<?php
    $id = $_GET['id'];
    if(!$id) {
        if (empty($json->ids))
            exit;

        if(sizeof($json->ids))
            $id = $json->ids[0];
        else exit;
    } else $json->ids[] = $id;

    $ids = implode(",", $json->ids);

    $u = new seTable('shop_order_payee', 'sop');
    $u->select('sop.*, (SELECT name_payment FROM shop_payment WHERE id=sop.payment_type) as name,
            CONCAT_WS(" ", p.last_name, p.first_name, p.sec_name) as payer, sua.in_payee');
    $u->innerjoin('person p','p.id=sop.id_author');
    $u->leftjoin('se_user_account sua', 'sua.id=sop.id_user_account_out');
    $u->where("sop.id IN (?)", $ids);
    $u->groupby('sop.id');

    $result = $u->getList();
    unset($u);

    $items = array();
    if(!empty($result)) {
        foreach($result as $item) {
            $payment = array();
            $payment['id'] = $item['id'];
            $payment['name'] = $item['name'];
            if (empty($payment['name']))
                $payment['name'] = 'С лицевого счета';
            $payment['idOrder'] = $item['id_order'];
            $payment['idPayer'] = $item['id_author'];
            $payment['payerName'] = $item['payer'];
            $payment['paymentTarget'] = (int) $item['payment_target'];
            $payment['idPaymentType'] = $item['payment_type'];
            $payment['idManager'] = $item['id_manager'];
            $payment['docNum'] = $item['num'];
            $payment['docDate'] = date('Y-m-d', strtotime($item['date']));
            $payment['docYear'] = (int) $item['year'];
            $payment['orderAmount'] = (real) $item['in_payee'];
            $payment['amount'] = (real) $item['amount'];
            $payment['note'] = $item['note'];
            $payment['idUserAccountIn'] = $item['id_user_account_in'];
            $payment['idUserAccountOut'] = $item['id_user_account_out'];
            $items[] = $payment;
        }
    }

    if (!se_db_error()) {
        $status['status'] = 'ok';
        $status['data'] = array('count'=>count($items), 'items'=>$items);
    } else {
        $status['status'] = 'error';
        $status['errortext'] = se_db_error();
    }
    outputData($status);
