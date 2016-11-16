<?php

se_db_query("ALTER TABLE shop_price
  ADD COLUMN min_count DOUBLE(10, 3) NOT NULL AFTER step_count;");