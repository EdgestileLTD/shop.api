<?php

$idsStr = implode(",", $json->ids);
se_db_query("UPDATE shop_price sp
                INNER JOIN shop_tovarorder st ON sp.id = st.id_price
                SET sp.presence_count = sp.presence_count + st.count
                WHERE st.id_order IN ({$idsStr}) AND sp.presence_count IS NOT NULL AND sp.presence_count >= 0");
se_db_query("UPDATE shop_modifications sm
                    INNER JOIN shop_tovarorder st ON sm.id IN (st.modifications)
                    INNER JOIN shop_price sp ON sp.id = st.id_price
                    SET sm.count = sm.count + st.count
                    WHERE st.id_order IN ({$idsStr}) AND sm.count IS NOT NULL AND sm.count >= 0");


if ($json->ids) {
    se_db_query("UPDATE shop_order SET is_delete = 'Y' WHERE id IN ({$idsStr})");
}

$status = array();
if (!se_db_error()) {
    $status['status'] = 'ok';
} else {
    $status['status'] = 'error';
    $status['errortext'] = "Не удаётся перевести указанные заказы в статус: 'Отмененные'";
}

outputData($status);