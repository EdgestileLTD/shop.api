<?php

se_db_query("CREATE TABLE shop_group_related (
  id int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  id_group int(10) UNSIGNED NOT NULL,
  id_related int(10) UNSIGNED NOT NULL,
  type tinyint(1) NOT NULL DEFAULT 1 COMMENT '1 - похожий, 2 - сопуствующий',
  is_cross tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Двухсторонний',
  updated_at timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  INDEX id_related (id_related),
  INDEX id_group (id_group),
  INDEX is_cross (is_cross),
  CONSTRAINT FK_shop_group_related_shop_group_id FOREIGN KEY (id_group)
  REFERENCES shop_group (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT FK_shop_group_related_shop_group_id_2 FOREIGN KEY (id_related)
  REFERENCES shop_group (id) ON DELETE CASCADE ON UPDATE CASCADE
)
ENGINE = INNODB
AUTO_INCREMENT = 1
CHARACTER SET utf8
COLLATE utf8_general_ci;");