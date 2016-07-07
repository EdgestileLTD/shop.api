<?php
$dd = date('Y-m-d', mktime(0,0,0,date('m'), date('d')-30, date('Y')));
$events = new seTable("shop_stat_session", 'sss');
$dateselect = (empty($json->full)) ?  "DATE_FORMAT(sss.created_at,'%Y-%m-%d') AS dateStat," : '';
$events->select($dateselect."COUNT(*) AS `sessionCount`,
(SELECT COUNT(*) FROM `shop_stat_events` WHERE event='add cart' AND created_at>'{$dd}') AS `addCartCount`, 
(SELECT COUNT(*) FROM `shop_stat_events` WHERE event='view shopcart' AND created_at>'{$dd}') AS `viewCartCount`, 
(SELECT COUNT(*) FROM `shop_stat_events` WHERE event='input contact' AND created_at>'{$dd}') AS `inputContactCount`, 
(SELECT COUNT(*) FROM `shop_stat_events` WHERE event='select payment' AND created_at>'{$dd}') AS `selectPaymentCount`, 
(SELECT COUNT(*) FROM `shop_stat_events` WHERE event='place order' AND created_at>'{$dd}') AS `placeOrderCount`, 
(SELECT COUNT(*) FROM `shop_stat_events` WHERE event='confirm order' AND created_at>'{$dd}') AS `confirmOrderCount`");
if (empty($json->full)){
    $events->groupby("dateStat", 1);
}
$events->where("created_at>'?'", $dd);
$items = $events->getList();
$status = array();
if (!se_db_error()) {
    $status['status'] = 'ok';
    $status['data'] = array('count'=>count($items), 'items'=>$items);
} else {
    $status['status'] = 'error';
    $status['errortext'] = se_db_error();
}
outputData($status);
