<?php

se_db_query("ALTER TABLE shop_modifications
  ADD COLUMN value_purchase DECIMAL(10, 2) UNSIGNED NOT NULL DEFAULT 0 AFTER value_opt_corp;");

se_db_query("ALTER TABLE shop_tovarorder
  ADD COLUMN price_purchase DECIMAL(10, 2) UNSIGNED DEFAULT NULL AFTER price;");