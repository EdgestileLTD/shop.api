<?php

se_db_query('ALTER TABLE shop_brand
  ADD COLUMN content TEXT DEFAULT NULL AFTER text;');