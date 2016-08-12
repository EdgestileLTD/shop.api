<?php

se_db_query("ALTER TABLE main
  ADD COLUMN sms_phone VARCHAR(255) DEFAULT NULL COMMENT 'Телефон для СМС информирование' AFTER folder;");

se_db_query("CREATE TABLE sms_providers (
  id int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  name varchar(50) DEFAULT NULL COMMENT 'Наименование шлюза',
  settings varchar(255) NOT NULL COMMENT 'настройки СМС шлюза (JSON формат)',
  is_active tinyint(1) DEFAULT 0,
  updated_at timestamp NOT NULL DEFAULT '0000-00-00 00:00:00' ON UPDATE CURRENT_TIMESTAMP,
  created_at timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
)
ENGINE = INNODB
AVG_ROW_LENGTH = 8192
CHARACTER SET utf8
COLLATE utf8_general_ci;");

se_db_query("CREATE TABLE sms_templates (
  id int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  code varchar(50) DEFAULT NULL,
  name varchar(50) DEFAULT NULL,
  text varchar(255) DEFAULT NULL,
  is_active tinyint(1) DEFAULT 1,
  updated_at timestamp NOT NULL DEFAULT '0000-00-00 00:00:00' ON UPDATE CURRENT_TIMESTAMP,
  created_at timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
)
ENGINE = INNODB
AVG_ROW_LENGTH = 16384
CHARACTER SET utf8
COLLATE utf8_general_ci;");