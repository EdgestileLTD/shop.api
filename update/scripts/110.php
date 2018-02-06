<?php

se_db_query('ALTER TABLE shop_accomp
  CHANGE COLUMN id_acc id_acc INT(10) UNSIGNED DEFAULT NULL,
  ADD COLUMN id_group INT(10) UNSIGNED DEFAULT NULL AFTER id_acc;');

se_db_query('ALTER TABLE shop_accomp
  ADD CONSTRAINT FK_shop_accomp_id_group FOREIGN KEY (id_group)
    REFERENCES shop_group(id) ON DELETE CASCADE ON UPDATE CASCADE;');