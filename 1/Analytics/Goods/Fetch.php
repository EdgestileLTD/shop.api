<?php

    $dateFilter = null;
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

    $u = new seTable('shop_price');
    $u->addField('price_purchase', 'decimal(10,2)');

    $query = "SELECT IFNULL(sp.name, 'Услуга') name, SUM(st.count) count, IFNULL((1 - (IFNULL(sp.price_purchase, sp.price)) / sp.price) * 100, 100)  profitability,
                IFNULL(SUM(sp.price - sp.price_purchase), AVG(st.price)) profit
                FROM shop_tovarorder st
                INNER JOIN shop_order so ON so.id = st.id_order AND so.status = 'Y' AND so.inpayee = 'N' AND ($dateFilter)
                LEFT JOIN shop_price sp ON st.id_price = sp.id
                GROUP BY sp.id";

    $stmt = se_db_query($query);
    while ($row = $stmt->fetch_assoc()) {
        $item = null;
        $item['name'] = $row['name'];
        $item['count'] = (int) $row['count'];
        $item['profitability'] = (real) $row['profitability'];
        $item['profit'] = (real) $row['profit'];
        $items[] = $item;
    }

    $status = array();
    if (!se_db_error()) {
        $status['status'] = 'ok';
        $status['data'] = array('count'=>count($items), 'items'=>$items);
    } else {
        $status['status'] = 'error';
        $status['errortext'] = se_db_error();
    }

    outputData($status);