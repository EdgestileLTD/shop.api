<?php

try {
    se_db_query("DELETE FROM shop_delivery_region WHERE id_delivery NOT IN (SELECT id FROM shop_deliverytype sd)");

    se_db_query("ALTER TABLE shop_delivery_region
      ADD CONSTRAINT FK_shop_delivery_region_shop_deliverytype_id FOREIGN KEY (id_delivery)
      REFERENCES shop_deliverytype(id) ON DELETE CASCADE ON UPDATE RESTRICT;");
}
catch (Exception $e) {

}
