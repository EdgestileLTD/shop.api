<?php
    se_db_query("CREATE TABLE IF NOT EXISTS shop_setting_groups (
                  id int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
                  name varchar(50) NOT NULL,
                  description varchar(255) DEFAULT NULL,
                  sort int(10) NOT NULL DEFAULT 0,
                  updated_at timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
                  created_at timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
                  PRIMARY KEY (id)
                )
                ENGINE = INNODB
                AVG_ROW_LENGTH = 16384
                CHARACTER SET utf8
                COLLATE utf8_general_ci;");

    se_db_query("CREATE TABLE IF NOT EXISTS shop_settings (
                  id int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
                  code varchar(100) NOT NULL,
                  type enum ('string', 'bool', 'select') NOT NULL DEFAULT 'string' COMMENT 'string - текстовое поле, bool - чекбокс, select - выбор из списка из поля list_values',
                  name varchar(100) NOT NULL COMMENT 'название параметра',
                  `default` varchar(100) NOT NULL COMMENT 'значение по умолчанию',
                  list_values varchar(255) DEFAULT NULL COMMENT 'список значений в формате value1|name1,value2|name2,value3|name3 ',
                  id_group int(10) UNSIGNED DEFAULT NULL,
                  description text DEFAULT NULL COMMENT 'описание параметра',
                  sort int(10) NOT NULL DEFAULT 0,
                  enabled tinyint(1) NOT NULL DEFAULT 1 COMMENT '0 - неактивный параметр',
                  updated_at timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
                  created_at timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
                  PRIMARY KEY (id),
                  UNIQUE INDEX UK_shop_settings (code),
                  CONSTRAINT FK_shop_settings_shop_setting_groups_id FOREIGN KEY (id_group)
                  REFERENCES shop_setting_groups (id) ON DELETE SET NULL ON UPDATE SET NULL
                )
                ENGINE = INNODB
                CHARACTER SET utf8
                COLLATE utf8_general_ci;");

    se_db_query("CREATE TABLE IF NOT EXISTS shop_setting_values (
                  id int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
                  id_main int(10) UNSIGNED NOT NULL,
                  id_setting int(10) UNSIGNED NOT NULL,
                  value varchar(100) NOT NULL,
                  updated_at timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
                  created_at timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
                  PRIMARY KEY (id),
                  UNIQUE INDEX UK_shop_setting_values (id_main, id_setting),
                  CONSTRAINT FK_shop_setting_values_main_id FOREIGN KEY (id_main)
                  REFERENCES main (id) ON DELETE CASCADE ON UPDATE CASCADE,
                  CONSTRAINT FK_shop_setting_values_shop_settings_id FOREIGN KEY (id_setting)
                  REFERENCES shop_settings (id) ON DELETE CASCADE ON UPDATE CASCADE
                )
                ENGINE = INNODB
                AVG_ROW_LENGTH = 8192
                CHARACTER SET utf8
                COLLATE utf8_general_ci;");