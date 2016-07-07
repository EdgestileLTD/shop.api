<?php

    se_db_query("ALTER TABLE shop_group
      ADD COLUMN id_modification_group_def INT(10) UNSIGNED DEFAULT NULL COMMENT 'Ид. группы модификаций по умолчанию' AFTER compare");

    se_db_query("ALTER TABLE shop_group
      ADD CONSTRAINT FK_shop_group_shop_modifications_group_id FOREIGN KEY (id_modification_group_def)
      REFERENCES shop_modifications_group(id) ON DELETE SET NULL ON UPDATE RESTRICT;");