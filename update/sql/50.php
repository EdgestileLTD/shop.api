<?php

se_db_query("ALTER TABLE shop_files
  ADD CONSTRAINT FK_shop_files_shop_price_id FOREIGN KEY (id_price)
    REFERENCES shop_price(id) ON DELETE CASCADE ON UPDATE RESTRICT;");