<?php

se_db_query("ALTER TABLE shop_feature
  ADD COLUMN is_market BOOLEAN NOT NULL DEFAULT 1 AFTER seo;");