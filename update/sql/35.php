<?php

se_db_query("ALTER TABLE shop_coupons
  ADD COLUMN id_user INT(10) UNSIGNED DEFAULT NULL AFTER id;");

se_db_query("ALTER TABLE shop_coupons
  ADD CONSTRAINT FK_shop_coupons_se_user_id FOREIGN KEY (id_user)
    REFERENCES se_user(id) ON DELETE SET NULL ON UPDATE RESTRICT;");