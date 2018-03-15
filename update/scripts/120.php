<?php

se_db_query('ALTER TABLE shop_price
  ADD COLUMN is_show_feature BOOL NOT NULL DEFAULT 1 AFTER enabled;');