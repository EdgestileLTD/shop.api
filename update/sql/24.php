<?php

se_db_query("
    CREATE TABLE shop_userfield_groups (
        id int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
      name varchar(255) NOT NULL,
      description text DEFAULT NULL,
      sort int(10) NOT NULL DEFAULT 0,
      enabled tinyint(1) NOT NULL DEFAULT 1,
      updated_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (id)
    )
    ENGINE = INNODB
    AVG_ROW_LENGTH = 16384
    CHARACTER SET utf8
    COLLATE utf8_general_ci;");

se_db_query("
    CREATE TABLE shop_userfields (
        id int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
      id_group int(10) UNSIGNED DEFAULT NULL,
      code varchar(255) NOT NULL,
      name varchar(255) NOT NULL,
      type enum ('string', 'text', 'select', 'checkbox', 'radio', 'date', 'number') NOT NULL DEFAULT 'string',
      required tinyint(1) NOT NULL DEFAULT 0,
      placeholder varchar(255) DEFAULT NULL,
      mask varchar(255) DEFAULT NULL,
      description text DEFAULT NULL,
      `values` text DEFAULT NULL,
      sort int(10) NOT NULL DEFAULT 0,
      enabled tinyint(1) NOT NULL DEFAULT 1,
      data enum ('contact', 'order') NOT NULL DEFAULT 'contact',
      min int(10) UNSIGNED DEFAULT NULL,
      max int(10) UNSIGNED DEFAULT NULL,
      updated_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (id),
      UNIQUE INDEX UK_shop_userfields_code (code),
      CONSTRAINT FK_shop_userfields_shop_userfield_groups_id FOREIGN KEY (id_group)
      REFERENCES shop_userfield_groups (id) ON DELETE RESTRICT ON UPDATE RESTRICT
    )
    ENGINE = INNODB
    AVG_ROW_LENGTH = 2730
    CHARACTER SET utf8
    COLLATE utf8_general_ci;");

se_db_query("
    CREATE TABLE shop_order_userfields (
        id int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
      id_order int(10) UNSIGNED NOT NULL,
      id_userfield int(10) UNSIGNED NOT NULL,
      value varchar(255) DEFAULT NULL,
      update_at timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      created_at timestamp NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (id),
      UNIQUE INDEX UK_shop_order_userfields (id_order, id_userfield),
      CONSTRAINT FK_shop_order_userfields_shop_order_id FOREIGN KEY (id_order)
      REFERENCES shop_order (id) ON DELETE CASCADE ON UPDATE CASCADE,
      CONSTRAINT FK_shop_order_userfields_shop_userfields_id FOREIGN KEY (id_userfield)
      REFERENCES shop_userfields (id) ON DELETE RESTRICT ON UPDATE RESTRICT
    )
    ENGINE = INNODB
    CHARACTER SET utf8
    COLLATE utf8_general_ci;");