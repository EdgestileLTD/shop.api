<?php

se_db_query("ALTER TABLE shop_price
  ADD COLUMN special_offer ENUM('Y','N') DEFAULT 'N' AFTER flag_hit;");

se_db_query("DELETE FROM shop_leader WHERE NOT id_price IN (SELECT sp.id FROM shop_price sp);");

se_db_query("UPDATE shop_price sp SET sp.special_offer = 'Y'
  WHERE sp.id IN (SELECT sl.id_price FROM shop_leader sl);");
;