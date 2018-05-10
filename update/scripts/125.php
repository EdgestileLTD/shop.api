<?php

se_db_query('ALTER TABLE shop_feature
  ADD COLUMN `code` VARCHAR(255) DEFAULT NULL AFTER name;');

se_db_query('ALTER TABLE shop_feature
  ADD UNIQUE INDEX UK_shop_feature_code (`code`);');

se_db_query('ALTER TABLE shop_feature_value_list
  ADD COLUMN `code` VARCHAR(255) DEFAULT NULL AFTER value;');

se_db_query('ALTER TABLE shop_feature_value_list
  ADD UNIQUE INDEX UK_shop_feature_value_list_cod (`code`);');

