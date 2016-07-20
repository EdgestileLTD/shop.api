<?php

se_db_query("ALTER TABLE shop_sameprice
  ADD COLUMN `cross` BOOLEAN NOT NULL DEFAULT 1 AFTER id_acc;");