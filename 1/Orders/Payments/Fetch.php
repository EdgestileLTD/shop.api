<?php

    $sql = "CREATE TABLE IF NOT EXISTS `shop_order_payee` (
        `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
        `id_order` int(10) unsigned DEFAULT NULL,
        `id_author` int(10) unsigned NOT NULL COMMENT 'Идентификатор плательщика',
        `num` mediumint(9) unsigned NOT NULL COMMENT 'Номер платежа',
        `date` datetime NOT NULL COMMENT 'Дата платежа',
        `year` smallint(6) unsigned NOT NULL DEFAULT '2000' COMMENT 'Год платежа',
        `payment_target` smallint(6) UNSIGNED DEFAULT 0 COMMENT 'Цель платежа: 0 - заказ, 1 - пополнение счёта',
        `payment_type` int(10) unsigned NOT NULL DEFAULT '1' COMMENT 'С лицевого счета: 0 или ид. платежа > 0 (таблица: shop_payment)',
        `id_payment` int(10) unsigned DEFAULT NULL,
        `id_manager` int(10) unsigned DEFAULT NULL COMMENT 'Идентификатор пользователя',
        `amount` decimal(10,2) unsigned NOT NULL COMMENT 'Сумма платежа',
        `curr` char(3) DEFAULT 'RUR' COMMENT 'Код валюты платежа',
        `note` varchar(255) DEFAULT NULL COMMENT 'Примечание к платежу',
        `id_user_account_in` int(10) unsigned DEFAULT NULL,
        `id_user_account_out` int(10) unsigned DEFAULT NULL,
        `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        `created_at` timestamp NULL DEFAULT '0000-00-00 00:00:00',
         PRIMARY KEY (`id`),
         KEY id_order (`id_order`),
         KEY id_author (`id_author`),
         CONSTRAINT FK_shop_order_payee_se_user_id FOREIGN KEY (id_author)
         REFERENCES se_user (id) ON DELETE CASCADE ON UPDATE RESTRICT,
         CONSTRAINT FK_shop_order_payee_shop_order_id FOREIGN KEY (id_order)
         REFERENCES shop_order (id) ON DELETE CASCADE ON UPDATE RESTRICT
        ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 COMMENT='Платежи к заказам';";
    se_db_query($sql);

    function convertFields($str) {
        $str = str_replace('[docDate]', 'DATE(`date`) ', $str);
        $str = str_replace('id ', 'sa.id ', $str);
        $str = str_replace('idOrder ', 'id_order ', $str);
        $str = str_replace('idPayer ', 'id_payer ', $str);
        $str = str_replace('docDate ', 'DATE(`date`) ', $str);
        $str = str_replace('paymentTarget', 'payment_target', $str);
        return $str;
    }

    $u = new seTable('shop_order_payee', 'sa');
    $u->select('sa.*, (SELECT name_payment FROM shop_payment WHERE id=sa.payment_type) as name,
        CONCAT_WS(" ", p.last_name, p.first_name, p.sec_name) as payer');
    $u->innerjoin('person p','p.id=sa.id_author');


    $search = $where = '';
    if (!empty($json->searchText)) {
        $filters = explode(" ", $json->searchText);
        foreach($filters as $filterItem){
            if (!empty($search))
                $search .= " AND ";
                $search .= "(id = '{$filterItem}'
                    OR sa.id_order = '{$filterItem}'
                    OR sa.amount = '{$filterItem}'
                    OR p.first_name like '%{$filterItem}%'
                    OR p.last_name like '%{$filterItem}%'
                    OR p.sec_name like '%{$filterItem}%')";
        }
    }

    if (!empty($json->filter))
        $where = $json->filter;
    if (!empty($search)) {
        if (!empty($where))
            $where = "(".$where.") AND (".$search.")";
        else $where = $search;
}
    if (!empty($where))
        $u->where(convertFields($where));

    $u->groupby('id');
    $patterns = array('id'=>'id',
        'name'=>'name',
        'idOrder'=>'id_order',
        'idPayer'=>'id_author',
        'payerName'=>'payer',
        'docNum'=>'num',
        'amount'=>'amount',
        'note'=>'note',
    );

    $sortBy = (isset($patterns[$json->sortBy])) ? $patterns[$json->sortBy] : 'id';
    $u->orderby($sortBy, $json->sortOrder === 'desc');

    $amount = 0;
    $sumResults = se_db_query('SELECT SUM(amount) total_sum, COUNT(*) total_count FROM('.$u->getSql().') sum_tbl');
    if ($sumResults && $row = se_db_fetch_assoc($sumResults)) {
        $amount = (real) $row['total_sum'];
        $count = (int) $row['total_count'];
    }
    $result = $u->getList($json->offset, $json->limit);
    $items = array();
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
        $payment['docDateDisplay'] = date('d.m.Y', strtotime($item['date']));
        $payment['docYear'] = (int) $item['year'];
        $payment['amount'] = (real) $item['amount'];
        $payment['note'] = $item['note'];
        $payment['idUserAccountIn'] = $item['id_user_account_in'];
        $payment['idUserAccountOut'] = $item['id_user_account_out'];
        $items[] = $payment;
    }
    unset($u);

    if (!se_db_error()) {
        $status['status'] = 'ok';
        $status['data'] = array('count'=>$count, 'totalAmount'=>$amount, 'items'=>$items);
    } else {
        $status['status'] = 'error';
        $status['errortext'] = se_db_error();
    }
    outputData($status);
