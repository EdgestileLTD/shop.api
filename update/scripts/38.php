<?php

se_db_query("ALTER TABLE shop_price
  ADD COLUMN market_category SMALLINT(6) UNSIGNED DEFAULT NULL AFTER is_market;");