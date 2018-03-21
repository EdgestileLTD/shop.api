CREATE TABLE shop_product_option_position (
  id int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  id_product int(10) UNSIGNED NOT NULL,
  id_option int(10) UNSIGNED NOT NULL,
  `position` tinyint(4) NOT NULL DEFAULT 1 COMMENT 'Позиция отображения: 0 - нигде, 1 - оба варианта, 2 - только снизу (основной контент), 3 - только справа (плавающий блок)',
  updated_at timestamp NULL DEFAULT NULL,
  created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE INDEX UK_shop_product_option (id_product, id_option),
  CONSTRAINT FK_shop_product_option_positi2 FOREIGN KEY (id_option)
  REFERENCES shop_option (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT FK_shop_product_option_positio FOREIGN KEY (id_product)
  REFERENCES shop_price (id) ON DELETE CASCADE ON UPDATE CASCADE
)
ENGINE = INNODB
AVG_ROW_LENGTH = 56
CHARACTER SET utf8
COLLATE utf8_general_ci
ROW_FORMAT = DYNAMIC;