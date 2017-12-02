<?php

se_db_query('ALTER TABLE shop_order
  CHANGE COLUMN id_admin id_admin INT(10) UNSIGNED DEFAULT NULL');

se_db_query("ALTER TABLE shop_order
  ADD CONSTRAINT FK_shop_order_id_admin FOREIGN KEY (id_admin)
    REFERENCES se_user(id) ON DELETE SET NULL ON UPDATE CASCADE");