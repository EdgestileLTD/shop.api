<?php

se_db_query('ALTER TABLE se_user
  ADD COLUMN is_manager BOOLEAN NOT NULL DEFAULT 0 AFTER is_super_admin');

se_db_query("
CREATE TABLE permission_role (
    id int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  name varchar(100) NOT NULL,
  description varchar(255) DEFAULT NULL,
  updated_at timestamp NOT NULL DEFAULT '0000-00-00 00:00:00' ON UPDATE CURRENT_TIMESTAMP,
  created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
)
ENGINE = INNODB
AUTO_INCREMENT = 1
CHARACTER SET utf8
COLLATE utf8_general_ci
COMMENT = 'Роли пользователей'");

se_db_query("
CREATE TABLE permission_role_user (
    id int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  id_role int(10) UNSIGNED NOT NULL,
  id_user int(10) UNSIGNED DEFAULT NULL,
  updated_at timestamp NOT NULL DEFAULT '0000-00-00 00:00:00' ON UPDATE CURRENT_TIMESTAMP,
  created_at timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE INDEX UK_permission_role_user (id_role, id_user),
  CONSTRAINT FK_permission_role_user_permission_role_id FOREIGN KEY (id_role)
  REFERENCES permission_role (id) ON DELETE CASCADE ON UPDATE RESTRICT,
  CONSTRAINT FK_permission_role_user_se_user_id FOREIGN KEY (id_user)
  REFERENCES se_user (id) ON DELETE CASCADE ON UPDATE RESTRICT
)
ENGINE = INNODB
AUTO_INCREMENT = 1
CHARACTER SET utf8
COLLATE utf8_general_ci;");

se_db_query("
CREATE TABLE permission_object (
    id int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  code varchar(100) NOT NULL,
  name varchar(255) NOT NULL,
  updated_at timestamp NOT NULL DEFAULT '0000-00-00 00:00:00' ON UPDATE CURRENT_TIMESTAMP,
  created_at timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
)
ENGINE = INNODB
AUTO_INCREMENT = 1
CHARACTER SET utf8
COLLATE utf8_general_ci;");

se_db_query("
CREATE TABLE permission_object_role (
    id int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  id_object int(10) UNSIGNED NOT NULL,
  id_role int(10) UNSIGNED NOT NULL,
  mask smallint(6) UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Маска прав (4 бита)',
  updated_at timestamp NOT NULL DEFAULT '0000-00-00 00:00:00' ON UPDATE CURRENT_TIMESTAMP,
  created_at timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT FK_permission_object_role_permission_object_id FOREIGN KEY (id_object)
  REFERENCES permission_object (id) ON DELETE CASCADE ON UPDATE RESTRICT,
  CONSTRAINT FK_permission_object_role_permission_role_id FOREIGN KEY (id_role)
  REFERENCES permission_role (id) ON DELETE CASCADE ON UPDATE RESTRICT
)
ENGINE = INNODB
AUTO_INCREMENT = 1
CHARACTER SET utf8
COLLATE utf8_general_ci;");