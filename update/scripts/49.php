<?php

se_db_query("CREATE TABLE shop_files (
  id int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  id_price int(10) UNSIGNED DEFAULT NULL,
  file varchar(255) NOT NULL COMMENT 'Имя файла в папке files',
  name varchar(255) DEFAULT NULL COMMENT 'Текст отображаемой ссылки на файл',
  updated_at timestamp NOT NULL DEFAULT '0000-00-00 00:00:00' ON UPDATE CURRENT_TIMESTAMP,
  created_at timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
)
ENGINE = INNODB
AUTO_INCREMENT = 1
CHARACTER SET utf8
COLLATE utf8_general_ci;");