<?php

se_db_query("ALTER TABLE shop_payment
  CHANGE COLUMN cutomer_type customer_type SMALLINT(6) UNSIGNED DEFAULT NULL COMMENT 'Тип контакта: 0 - для всех, 1 - только для физ.лиц, 2 - только для компаний';");