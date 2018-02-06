<?php

se_db_query("CREATE TABLE shop_product_type (
      id int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
      name varchar(255) NOT NULL,
      updated_at timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
      created_at timestamp NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (id)
    )
    ENGINE = INNODB
    AUTO_INCREMENT = 1
    CHARACTER SET utf8
    COLLATE utf8_general_ci
    COMMENT = 'Типы товаров';");

se_db_query("CREATE TABLE shop_product_type_feature (
      id int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
      id_type int(10) UNSIGNED NOT NULL,
      id_feature int(10) UNSIGNED NOT NULL,
      updated_at timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
      created_at timestamp NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (id),
      CONSTRAINT FK_shop_product_type_feature_shop_feature_id FOREIGN KEY (id_feature)
      REFERENCES shop_feature (id) ON DELETE CASCADE ON UPDATE RESTRICT,
      CONSTRAINT FK_shop_product_type_feature_shop_product_type_id FOREIGN KEY (id_type)
      REFERENCES shop_product_type (id) ON DELETE CASCADE ON UPDATE RESTRICT
    )
    ENGINE = INNODB
    AUTO_INCREMENT = 1
    CHARACTER SET utf8
    COLLATE utf8_general_ci
    COMMENT = 'Связка типов товаров с параметрами';");

se_db_query("ALTER TABLE shop_price
    ADD COLUMN id_type INT(10) UNSIGNED DEFAULT NULL COMMENT 'Тип товара' AFTER id_brand;");

se_db_query("ALTER TABLE shop_price
    ADD CONSTRAINT FK_shop_price_shop_product_type_id FOREIGN KEY (id_type)
    REFERENCES shop_product_type(id) ON DELETE SET NULL ON UPDATE RESTRICT;");