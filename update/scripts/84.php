<?php

se_db_query("ALTER TABLE shop_price
  ADD COLUMN rate DECIMAL(10, 2) DEFAULT NULL AFTER enabled");