<?php
    se_db_query("ALTER TABLE shop_price
                  CHANGE COLUMN volume volume DECIMAL(10, 3) UNSIGNED DEFAULT NULL,
                  CHANGE COLUMN weight weight DECIMAL(10, 3) UNSIGNED DEFAULT NULL");