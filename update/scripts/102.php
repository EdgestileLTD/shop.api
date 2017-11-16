<?php

se_db_query('ALTER TABLE shop_brand
  ADD COLUMN sort INT(11) DEFAULT NULL AFTER description;');