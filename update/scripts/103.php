<?php

se_db_query("ALTER TABLE shop_group
  ADD COLUMN bread_crumb VARCHAR(255) DEFAULT NULL AFTER description;");

se_db_query("ALTER TABLE shop_price
    ADD COLUMN bread_crumb VARCHAR(255) DEFAULT NULL AFTER description;");