<?php

se_db_query("ALTER TABLE shop_delivery
  ADD COLUMN id_subdelivery INT(10) UNSIGNED DEFAULT NULL AFTER id_order;");

se_db_query("ALTER TABLE shop_delivery
  ADD CONSTRAINT FK_shop_delivery_shop_deliverytype_id FOREIGN KEY (id_subdelivery)
    REFERENCES shop_deliverytype(id) ON DELETE RESTRICT ON UPDATE RESTRICT;");