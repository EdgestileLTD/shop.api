<?php

se_db_query("ALTER TABLE shop_price
  CHANGE COLUMN `code` `code` VARCHAR(255) NOT NULL;");