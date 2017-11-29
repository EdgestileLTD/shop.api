<?php

se_db_query("ALTER TABLE shop_group
  CHANGE COLUMN bread_crumb page_title VARCHAR(255) DEFAULT NULL");

se_db_query("ALTER TABLE shop_price
  CHANGE COLUMN bread_crumb page_title VARCHAR(255) DEFAULT NULL");