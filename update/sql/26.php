<?php

se_db_query("ALTER TABLE shop_coupons
  CHANGE COLUMN type type ENUM('p','a','g') NOT NULL DEFAULT 'p';");