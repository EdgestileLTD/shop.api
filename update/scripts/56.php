<?php

se_db_query("ALTER TABLE shop_price
  ADD COLUMN sort INT(10) UNSIGNED DEFAULT NULL AFTER market_category;");