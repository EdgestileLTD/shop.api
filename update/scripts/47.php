<?php

se_db_query("ALTER TABLE shop_discounts
  ADD COLUMN sort INT(10) UNSIGNED DEFAULT NULL AFTER week;");