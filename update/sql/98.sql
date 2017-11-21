CREATE TABLE IF NOT EXISTS shop_option_group (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `sort` int(10) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) UNSIGNED NOT NULL DEFAULT 1,
  `updated_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
)
ENGINE = INNODB
CHARACTER SET utf8
COLLATE utf8_general_ci;

CREATE TABLE  IF NOT EXISTS shop_option (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_group` int(10) UNSIGNED DEFAULT NULL COMMENT 'Возможность несколько опций объединить в группу',
  `code` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `note` text,
  `description` text DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `type` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Тип опции для отображения на сайте, 0 - радиокнопки, 1 - список, 2 - чекбокс (множественный выбор)',
  `type_price` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Тип влияния на конечную стоимость товара, 0 - абсолютное значение, 1 - процент',
  `sort` int(10) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) UNSIGNED NOT NULL DEFAULT 1,
  `is_counted` tinyint(1) UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Может ли пользователь изменять количество значения опции',
  `updated_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  INDEX id_group (id_group),
  CONSTRAINT FK_shop_option_id_group FOREIGN KEY (id_group)
  REFERENCES shop_option_group (id) ON DELETE RESTRICT ON UPDATE RESTRICT
)
ENGINE = INNODB
CHARACTER SET utf8
COLLATE utf8_general_ci;

CREATE TABLE IF NOT EXISTS shop_option_value (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_option` int(10) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `price` DOUBLE(10, 2) NOT NULL DEFAULT 0 COMMENT 'Базовая стоимость опции',
  `price_type` int(10) UNSIGNED DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `sort` int(10) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) UNSIGNED NOT NULL DEFAULT 1,
  `updated_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  INDEX id_option (id_option),
  CONSTRAINT FK_shop_option_value_id_option FOREIGN KEY (id_option)
  REFERENCES shop_option (id) ON DELETE RESTRICT ON UPDATE RESTRICT
)
ENGINE = INNODB
CHARACTER SET utf8
COLLATE utf8_general_ci;

CREATE TABLE IF NOT EXISTS shop_product_option (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_product` int(10) UNSIGNED NOT NULL,
  `id_option_value` int(10) UNSIGNED NOT NULL,
  `price` DOUBLE(10, 2) NOT NULL DEFAULT 0,
  `sort` int(10) NOT NULL DEFAULT 0,
  `is_default` tinyint(1) UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Будет ли значение опции выбранно по умолчанию',
  `updated_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  INDEX id_product (id_product),
  INDEX id_option_value (id_option_value),
  UNIQUE INDEX UK_shop_product_option (id_product, id_option_value),
  CONSTRAINT FK_shop_product_option_id_opti FOREIGN KEY (id_option_value)
  REFERENCES shop_option_value (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT FK_shop_product_option_id_prod FOREIGN KEY (id_product)
  REFERENCES shop_price (id) ON DELETE CASCADE ON UPDATE CASCADE
)
ENGINE = INNODB
CHARACTER SET utf8
COLLATE utf8_general_ci;

CREATE TABLE IF NOT EXISTS shop_tovarorder_option (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_tovarorder` int(10) UNSIGNED NOT NULL,
  `id_option_value` int(10) UNSIGNED NOT NULL,
  `price` DOUBLE(10, 2) NOT NULL DEFAULT 0,
  `base_price` double(10, 2) NOT NULL DEFAULT 0.00,
  `count` DOUBLE(10, 3) NOT NULL DEFAULT 0,
  `updated_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  INDEX id_tovarorder (id_tovarorder),
  INDEX id_option_value (id_option_value),
  CONSTRAINT FK_shop_tovarorder_option_id_o FOREIGN KEY (id_option_value)
  REFERENCES shop_option_value (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT FK_shop_tovarorder_option_id_t FOREIGN KEY (id_tovarorder)
  REFERENCES shop_tovarorder (id) ON DELETE CASCADE ON UPDATE CASCADE
)
ENGINE = INNODB
CHARACTER SET utf8
COLLATE utf8_general_ci;