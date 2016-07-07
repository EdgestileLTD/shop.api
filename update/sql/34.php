<?php

se_db_query("CREATE TABLE integration (
  id int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  name varchar(255) NOT NULL COMMENT 'Наименование сервиса',
  url_oauth varchar(255) DEFAULT NULL,
  url_api varchar(255) DEFAULT NULL,
  note text DEFAULT NULL,
  is_active tinyint(1) NOT NULL DEFAULT 1,
  updated_at timestamp NOT NULL DEFAULT '0000-00-00 00:00:00' ON UPDATE CURRENT_TIMESTAMP,
  created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
)
ENGINE = INNODB
AVG_ROW_LENGTH = 16384
CHARACTER SET utf8
COLLATE utf8_general_ci;");

se_db_query("INSERT INTO integration(name, url_oauth, url_api, note, is_active) VALUES
('Яндекс.Фото', 'http://upload.beget.edgestile.net/api/integrations/YandexPhotos/auth.php', 'http://api-fotki.yandex.ru', NULL, 1);");

se_db_query("CREATE TABLE integration_oauth (
  id int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  id_integration int(10) UNSIGNED NOT NULL COMMENT 'Ид. интеграции',
  token varchar(255) DEFAULT NULL,
  expired datetime DEFAULT NULL,
  login varchar(255) DEFAULT NULL,
  updated_at timestamp NOT NULL DEFAULT '0000-00-00 00:00:00' ON UPDATE CURRENT_TIMESTAMP,
  created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT FK_integration_oauth_integration_id FOREIGN KEY (id_integration)
  REFERENCES integration (id) ON DELETE CASCADE ON UPDATE RESTRICT
)
ENGINE = INNODB
CHARACTER SET utf8
COLLATE utf8_general_ci
COMMENT = 'Настройки для oauth';");