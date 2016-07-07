<?php

se_db_query("CREATE TABLE shop_variables (
  id int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  name varchar(255) NOT NULL,
  value varchar(255) DEFAULT NULL,
  updated_at timestamp NOT NULL DEFAULT '0000-00-00 00:00:00' ON UPDATE CURRENT_TIMESTAMP,
  created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE INDEX UK_shop_variables_name (name)
)
ENGINE = INNODB
AVG_ROW_LENGTH = 3276
CHARACTER SET utf8
COLLATE utf8_general_ci;");