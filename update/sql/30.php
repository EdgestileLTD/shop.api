<?php

se_db_query("CREATE TABLE shop_section (
      id int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
      code varchar(40) NOT NULL COMMENT 'Код раздела',
      updated_at timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
      created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (id),
      UNIQUE INDEX code (code)
    )
    ENGINE = INNODB
    AVG_ROW_LENGTH = 4096
    CHARACTER SET utf8
    COLLATE utf8_general_ci
    COMMENT = 'Разделы главной страницы';");

se_db_query("CREATE TABLE shop_section_page (
  id int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  id_section int(10) UNSIGNED NOT NULL,
  title varchar(255) DEFAULT NULL,
  page varchar(255) DEFAULT NULL,
  se_section varchar(10) DEFAULT NULL,
  enabled tinyint(1) NOT NULL DEFAULT 1,
  updated_at timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE INDEX UK_shop_section_page (page, se_section, id_section),
  CONSTRAINT FK_shop_section_page_shop_section_id FOREIGN KEY (id_section)
  REFERENCES shop_section (id) ON DELETE CASCADE ON UPDATE RESTRICT
)
ENGINE = INNODB
AVG_ROW_LENGTH = 4096
CHARACTER SET utf8
COLLATE utf8_general_ci;");

se_db_query("CREATE TABLE shop_section_item (
  id int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  id_section int(10) UNSIGNED NOT NULL,
  name varchar(255) NOT NULL,
  note varchar(255) DEFAULT NULL,
  id_price int(10) UNSIGNED DEFAULT NULL COMMENT 'Ид. элемента из таблицы shop_price',
  id_group int(10) UNSIGNED DEFAULT NULL COMMENT 'Ид. элемента из таблицы shop_group',
  id_brand int(10) UNSIGNED DEFAULT NULL COMMENT 'Ид. элемента из таблицы shop_brand',
  id_new int(10) UNSIGNED DEFAULT NULL COMMENT 'Ид. элемента из таблицы news',
  url varchar(255) DEFAULT NULL,
  picture varchar(255) DEFAULT NULL,
  picture_alt varchar(255) DEFAULT NULL,
  sort smallint(6) UNSIGNED NOT NULL DEFAULT 0,
  enabled tinyint(1) NOT NULL DEFAULT 1,
  updated_at timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT FK_shop_section_item_news_id FOREIGN KEY (id_new)
  REFERENCES news (id) ON DELETE CASCADE ON UPDATE RESTRICT,
  CONSTRAINT FK_shop_section_item_shop_brand_id FOREIGN KEY (id_brand)
  REFERENCES shop_brand (id) ON DELETE CASCADE ON UPDATE RESTRICT,
  CONSTRAINT FK_shop_section_item_shop_group_id FOREIGN KEY (id_group)
  REFERENCES shop_group (id) ON DELETE CASCADE ON UPDATE RESTRICT,
  CONSTRAINT FK_shop_section_item_shop_price_id FOREIGN KEY (id_price)
  REFERENCES shop_price (id) ON DELETE CASCADE ON UPDATE RESTRICT,
  CONSTRAINT FK_start_section_item_start_section_id FOREIGN KEY (id_section)
  REFERENCES shop_section (id) ON DELETE CASCADE ON UPDATE RESTRICT
)
ENGINE = INNODB
AVG_ROW_LENGTH = 3276
CHARACTER SET utf8
COLLATE utf8_general_ci
COMMENT = 'Элементы разделов';");