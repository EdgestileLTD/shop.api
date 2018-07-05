ALTER TABLE shop_group_related
  CHANGE COLUMN is_cross is_cross TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Двухсторонний';