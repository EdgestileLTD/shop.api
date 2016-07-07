<?php
    
    
    $u = new seTable('shop_order','so');
    $u->select("COUNT(*) as `countOrders`");
    $u->where("is_delete='N'");
    $u->fetchOne();
    $countorders = intval($u->countOrders);
    $date_list = array();
    for ($i = 0; $i < 31; $i++){
      $date_list[] = date('Y-m-d', mktime(0,0,0,date('m'), date('d')-$i, date('Y')));
    }

    $u = new seTable('shop_order','so');
    $u->select("so.date_order AS dateOrder, COUNT(*) as `allOrders`,
    (SELECT SUM((price-discount)*count) FROM shop_tovarorder WHERE id_order IN (SELECT id FROM shop_order WHERE date_order=so.date_order AND is_delete='N')) AS ordersSumm,
    (SELECT COUNT(*) FROM shop_order WHERE status='Y' AND date_order=so.date_order AND is_delete='N') AS payOrders,
    (SELECT COUNT(*) FROM shop_order WHERE status='Y' AND date_payee=so.date_order AND is_delete='N') AS payOrdersD");
    $u->groupby('so.date_order');
    $u->orderby('dateOrder', true);
    $u->where("is_delete='N'");
    if (!empty($json->dateStart)){
        $u->where("date_order >='?'", $json->dateStart);
    }
    if (!empty($json->dateEnd)){
        $u->where("date_order <='?'", $json->dateEnd);
    }
    $count = $u->getListCount();
    $items = $u->getList($json->offset, $json->limit);
    unset($u);

    if (!se_db_error()) {
        $status['status'] = 'ok';
        $status['data'] = array('count'=>$count, 'countOrders'=>$countorders,'items'=>$items);
    } else {
        $status['status'] = 'error';
        $status['errortext'] = se_db_error();
    }
    outputData($status);
