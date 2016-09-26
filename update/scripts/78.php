<?php

se_db_query("CREATE TABLE email_providers (
    id int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  name varchar(50) DEFAULT NULL COMMENT 'Наименование шлюза',
  url varchar(255) DEFAULT NULL,
  settings varchar(255) NOT NULL COMMENT 'настройки email сервиса (JSON формат)',
  is_active tinyint(1) DEFAULT 0,
  updated_at timestamp NOT NULL DEFAULT '0000-00-00 00:00:00' ON UPDATE CURRENT_TIMESTAMP,
  created_at timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
)
ENGINE = INNODB
AVG_ROW_LENGTH = 8192
CHARACTER SET utf8
COLLATE utf8_general_ci;");

se_db_query("ALTER TABLE sms_providers
  ADD COLUMN url VARCHAR(255) DEFAULT NULL AFTER name;");

se_db_query("UPDATE sms_providers sp SET sp.url = sp.name;");

se_db_query("INSERT INTO email_providers(name, url, settings, is_active) VALUES
('sendpulse', 'https://login.sendpulse.com', '{\"ID\":{\"type\":\"string\",\"value\":\"\"}, \"SECRET\":{\"type\":\"string\",\"value\":\"\"}}', 1);");

se_db_query("ALTER TABLE se_group
  ADD COLUMN email_settings VARCHAR(255) DEFAULT NULL COMMENT 'Настройки для email рассылок' AFTER id_parent;");