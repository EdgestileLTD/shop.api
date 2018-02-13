<?php

se_db_query('CREATE TABLE shop_label (
  id int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  image varchar(255) DEFAULT NULL,
  name varchar(255) NOT NULL,
  code varchar(20) NOT NULL,
  updated_at timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
)
ENGINE = INNODB
AVG_ROW_LENGTH = 8192
CHARACTER SET utf8
COLLATE utf8_general_ci
ROW_FORMAT = DYNAMIC;');

se_db_query('CREATE TABLE shop_label_product (
  id int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  id_label int(10) UNSIGNED NOT NULL,
  id_product int(10) UNSIGNED NOT NULL,
  updated_at timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT FK_shop_label_product_id_label FOREIGN KEY (id_label)
  REFERENCES shop_label (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT FK_shop_label_product_id_produ FOREIGN KEY (id_product)
  REFERENCES shop_price (id) ON DELETE CASCADE ON UPDATE CASCADE
)
ENGINE = INNODB
AVG_ROW_LENGTH = 8192
CHARACTER SET utf8
COLLATE utf8_general_ci
ROW_FORMAT = DYNAMIC;');