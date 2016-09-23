<?php

se_db_query("ALTER TABLE shop_setting_values
  CHANGE COLUMN id_main id_main INT(10) UNSIGNED DEFAULT NULL;");