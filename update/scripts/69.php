<?php

se_db_query("ALTER TABLE shop_userfields
  CHANGE COLUMN data data ENUM('contact','order','company') NOT NULL DEFAULT 'contact'");

se_db_query("ALTER TABLE shop_userfield_groups
  CHANGE COLUMN updated_at updated_at TIMESTAMP NOT NULL ON UPDATE CURRENT_TIMESTAMP");

se_db_query("ALTER TABLE shop_userfield_groups
  ADD COLUMN data ENUM('contact','order','company') DEFAULT NULL AFTER enabled");

se_db_query("UPDATE shop_userfields su SET su.data = 'order'");

se_db_query("UPDATE shop_userfield_groups sug SET sug.data = 'order'");

se_db_query("CREATE TABLE person_userfields (
    id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  id_person INT(10) UNSIGNED NOT NULL,
  id_userfield INT UNSIGNED NOT NULL,
  value TEXT DEFAULT NULL,
  updated_at TIMESTAMP NOT NULL ON UPDATE CURRENT_TIMESTAMP,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT FK_person_userfields_se_user_id FOREIGN KEY (id_person)
    REFERENCES se_user(id) ON DELETE CASCADE ON UPDATE RESTRICT,
  CONSTRAINT FK_person_userfields_shop_userfields_id FOREIGN KEY (id_userfield)
    REFERENCES shop_userfields(id) ON DELETE CASCADE ON UPDATE RESTRICT
)
ENGINE = INNODB");

se_db_query("CREATE TABLE company_userfields (
    id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  id_company INT(10) UNSIGNED NOT NULL,
  id_userfield INT UNSIGNED NOT NULL,
  value TEXT DEFAULT NULL,
  updated_at TIMESTAMP NOT NULL ON UPDATE CURRENT_TIMESTAMP,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT FK_company_userfields_company_id FOREIGN KEY (id_company)
    REFERENCES company(id) ON DELETE CASCADE ON UPDATE RESTRICT,
  CONSTRAINT FK_company_userfields_shop_userfields_id FOREIGN KEY (id_userfield)
    REFERENCES shop_userfields(id) ON DELETE CASCADE ON UPDATE RESTRICT
)
ENGINE = INNODB");