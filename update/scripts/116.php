<?php

se_db_query('ALTER TABLE shop_label
  ADD COLUMN `sort` INT(10) UNSIGNED NOT NULL AFTER `code`;');