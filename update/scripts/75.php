<?php

se_db_query("ALTER TABLE shop_delivery
  CHANGE COLUMN postindex postindex VARCHAR(255) DEFAULT NULL;");