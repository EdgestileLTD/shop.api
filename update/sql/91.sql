CREATE TABLE import_profile (
  id int(10) NOT NULL AUTO_INCREMENT,
  name varchar(255) NOT NULL,
  settings text DEFAULT NULL COMMENT 'Настройки в json формате',
  updated_at timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  created_at timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
)
ENGINE = INNODB
AVG_ROW_LENGTH = 8192
CHARACTER SET utf8
COLLATE utf8_general_ci
ROW_FORMAT = DYNAMIC;