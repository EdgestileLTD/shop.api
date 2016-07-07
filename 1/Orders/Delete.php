<?php

    $idsStr = implode(",", $json->ids);
    // возврат товаров
    se_db_query("UPDATE shop_price sp
                INNER JOIN shop_tovarorder st ON sp.id = st.id_price
                SET sp.presence_count = sp.presence_count + st.count
                WHERE st.id_order IN ({$idsStr}) AND sp.presence_count IS NOT NULL AND sp.presence_count >= 0");
    // возврат модификаций
    se_db_query("UPDATE shop_modifications sm
                    INNER JOIN shop_tovarorder st ON sm.id IN (st.modifications)
                    INNER JOIN shop_price sp ON sp.id = st.id_price
                    SET sm.count = sm.count + st.count
                    WHERE st.id_order IN ({$idsStr}) AND sm.count IS NOT NULL AND sm.count >= 0");


    if ($json->ids) {
        $u = new seTable('shop_order','so');
        $u->where('id in (?)', $idsStr)->deletelist();
    }

    $status = array();
    if (!mysql_error()) {
        $status['status'] = 'ok';
    } else {
        $status['status'] = 'error';
        $status['errortext'] = mysql_error();
    }

    outputData($status);