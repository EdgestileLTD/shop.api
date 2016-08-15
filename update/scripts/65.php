<?php

se_db_query("CREATE TABLE sms_log (
  id int(11) NOT NULL AUTO_INCREMENT,
  date datetime NOT NULL,
  id_sms varchar(50) NOT NULL,
  id_provider int(10) UNSIGNED NOT NULL,
  id_user int(10) UNSIGNED DEFAULT NULL,
  phone varchar(255) DEFAULT NULL,
  code int(11) UNSIGNED DEFAULT NULL,
  status varchar(255) DEFAULT NULL,
  text varchar(255) DEFAULT NULL,
  cost decimal(19, 2) DEFAULT NULL,
  count int(11) UNSIGNED DEFAULT NULL,
  updated_at timestamp NOT NULL DEFAULT '0000-00-00 00:00:00' ON UPDATE CURRENT_TIMESTAMP,
  created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
)
ENGINE = INNODB
CHARACTER SET utf8
COLLATE utf8_general_ci;");