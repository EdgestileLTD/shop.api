<?php

se_db_query("
CREATE TABLE IF NOT EXISTS shop_setting_values (
      id int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
      id_main int(10) UNSIGNED NOT NULL,
      id_setting int(10) UNSIGNED NOT NULL,
      value varchar(100) NOT NULL,
      updated_at timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
      created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (id),
      UNIQUE INDEX UK_shop_setting_values (id_main, id_setting),
      CONSTRAINT FK_shop_setting_values_shop_settings_id FOREIGN KEY (id_setting)
      REFERENCES shop_settings (id) ON DELETE CASCADE ON UPDATE CASCADE
    )
    ENGINE = INNODB
    AVG_ROW_LENGTH = 8192
    CHARACTER SET utf8
    COLLATE utf8_general_ci;");