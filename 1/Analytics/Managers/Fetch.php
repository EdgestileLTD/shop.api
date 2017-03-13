<?php

    $dateFilter = '';
    $dateFrom = $json->dateFrom;
    $dateTo = $json->dateTo;
    if (!empty($dateFrom))
        $dateFilter = "so.date_order >= '$dateFrom'";
    if (!empty($dateTo)) {
        if (!empty($dateFilter))
            $dateFilter .= " AND ";
        $dateFilter .= "so.date_order <= '$dateTo'";
    }
    if (!$dateFilter)
        $dateFilter = "TRUE";

    $u = new seTable('shop_order', 'so');
    $u->addField('managers', "TEXT");

    $query = "SELECT idManager, SUM(a.countOrders) countOrders FROM(
                SELECT IFNULL(so.managers, 0) idManager, COUNT(DISTINCT(so.id_author)) countOrders
                  FROM shop_order so
                  WHERE so.inpayee = 'N' AND ($dateFilter)
                  GROUP BY so.managers) a GROUP BY idManager";

    $stmt = se_db_query($query);
    while ($row = $stmt->fetch_assoc()) {
        $item = null;
        $mans = explode(',', $row['idManager']);
        $mcnt = count($mans);
        foreach($mans as $it){
            $items[$it]['idManager'] = $it;
            $items[$it]['countPaidOrders'] = 0;
            $items[$it]['averageCheck'] = 0;
            $items[$it]['countOrders'] += round($row['countOrders'] / $mcnt, 1);
        }
    }

    $sql = $query = "SELECT idManager, SUM(a.countPaidOrders) countPaidOrders, SUM(sumPaidOrders) sumPaidOrders FROM(
                SELECT IFNULL(so.managers, 0) idManager, COUNT(DISTINCT(so.id_author)) countPaidOrders,
                  SUM((st.price-IFNULL(st.discount, 0))*st.count-IFNULL(so.discount, 0) + IFNULL(so.delivery_payee, 0)) sumPaidOrders
                  FROM shop_order so
                  INNER JOIN shop_tovarorder st ON st.id_order = so.id
                  WHERE so.inpayee = 'N' AND so.status = 'Y' AND ($dateFilter)
                  GROUP BY so.managers) a GROUP BY idManager";


    $resultItems = array();
    $stmt = se_db_query($query);
    while ($row = $stmt->fetch_assoc()) {
        $mans = explode(',', $row['idManager']);
        $mcnt = count($mans);
        foreach($mans as $it){
        $items[$it]['countPaidOrders'] += round($row['countPaidOrders'] / $mcnt, 1);
        $items[$it]['averageCheck'] += round($row['sumPaidOrders'] / $row['countPaidOrders'] / $mcnt, 2);
        $items[$it]['totalCheck'] += $row['sumPaidOrders'];
        }
    }
    
    foreach($items as $it){
        $resultItems[] = $it;
    }

    //print_r($items);

    $status = array();
    if (!se_db_error()) {
        $status['status'] = 'ok';
        $status['data'] = array('count'=>count($items), 'items'=>$resultItems);
    } else {
        $status['status'] = 'error';
        $status['error'] = se_db_error();
    }

    outputData($status);