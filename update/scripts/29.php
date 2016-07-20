<?php

se_db_query("ALTER TABLE shop_price
      CHANGE COLUMN is_market is_market TINYINT(1) NOT NULL DEFAULT 1;");
    se_db_query("UPDATE shop_price sp SET sp.is_market = 1");