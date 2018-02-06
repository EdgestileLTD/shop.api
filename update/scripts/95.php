<?php

se_db_query("CREATE TABLE shop_measure_weight (
  id int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  code varchar(255) DEFAULT NULL COMMENT 'Код по ОКЕИ',
  name varchar(255) NOT NULL,
  designation varchar(50) DEFAULT NULL COMMENT 'Условное обозначение',
  value double(10, 6) DEFAULT NULL COMMENT 'Мера',
  is_base tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Признак базовой меры',
  `precision` int(11) DEFAULT NULL COMMENT 'Точность числа (кол-во знаков после запятой)',
  updated_at timestamp NULL DEFAULT NULL,
  created_at timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
)
ENGINE = INNODB
AVG_ROW_LENGTH = 8192
CHARACTER SET utf8
COLLATE utf8_general_ci");

se_db_query("CREATE TABLE shop_measure_volume (
  id int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  code varchar(255) DEFAULT NULL COMMENT 'Код по ОКЕИ',
  name varchar(255) NOT NULL,
  designation varchar(50) DEFAULT NULL COMMENT 'Условное обозначение',
  value double(10, 6) DEFAULT NULL COMMENT 'Мера',
  is_base tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Признак базовой меры',
  `precision` int(11) DEFAULT NULL COMMENT 'Точность числа (кол-во знаков после запятой)',
  updated_at timestamp NULL DEFAULT NULL,
  created_at timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
)
ENGINE = INNODB
AVG_ROW_LENGTH = 8192
CHARACTER SET utf8
COLLATE utf8_general_ci;");

se_db_query("CREATE TABLE shop_price_measure (
  id int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  id_price int(11) UNSIGNED NOT NULL,
  id_weight_view int(11) UNSIGNED DEFAULT NULL,
  id_weight_edit int(11) UNSIGNED DEFAULT NULL,
  id_volume_view int(11) UNSIGNED DEFAULT NULL,
  id_volume_edit int(11) UNSIGNED DEFAULT NULL,
  updated_at timestamp NULL DEFAULT NULL,
  created_at timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT FK_price_measure_id_volume_e FOREIGN KEY (id_volume_edit)
  REFERENCES shop_measure_volume (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT FK_price_measure_id_volume_v FOREIGN KEY (id_volume_view)
  REFERENCES shop_measure_volume (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT FK_price_measure_id_weight_e FOREIGN KEY (id_weight_edit)
  REFERENCES shop_measure_weight (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT FK_price_measure_id_weight_v FOREIGN KEY (id_weight_view)
  REFERENCES shop_measure_weight (id) ON DELETE CASCADE ON UPDATE CASCADE
)
ENGINE = INNODB
CHARACTER SET utf8
COLLATE utf8_general_ci;");

se_db_query("INSERT INTO shop_setting_groups(id, name, description, sort) VALUES
(20, 'Параметры мер', 'Параметры мер веса и объема', 2)");

se_db_query("INSERT INTO shop_settings(code, type, name, `default`, list_values, id_group, description, sort, enabled) VALUES
('weight_view', 'string', 'Код отображаемой меры веса', '163', NULL, 20, 'Код ОКЕИ отображаемой меры веса', 0, 1);");
se_db_query("INSERT INTO shop_settings(code, type, name, `default`, list_values, id_group, description, sort, enabled) VALUES
('volume_view', 'string', 'Код отображаемой меры объема', '111', NULL, 20, 'Код ОКЕИ отображаемой меры обема', 1, 1);");
se_db_query("INSERT INTO shop_settings(code, type, name, `default`, list_values, id_group, description, sort, enabled) VALUES
('weight_edit', 'string', 'Код редактируемой меры веса', '163', NULL, 20, 'Код ОКЕИ редактируемой меры веса', 2, 1);");
se_db_query("INSERT INTO shop_settings(code, type, name, `default`, list_values, id_group, description, sort, enabled) VALUES
('volume_edit', 'string', 'Код редактируемой меры объема', '111', NULL, 20, 'Код ОКЕИ редактируемой меры объема', 3, 1);");

se_db_query("INSERT INTO shop_measure_volume(code, name, designation, value, is_base, `precision`) VALUES
('111', 'Кубический сантиметр', 'см3', 1.000000, 1, 0),
('112', 'Литр', 'л', 0.001000, 0, 3);");

se_db_query("INSERT INTO shop_measure_weight(code, name, designation, value, is_base, `precision`) VALUES
('163', 'Грамм', 'г', 1.000000, 1, 0),
('166', 'Килограмм', 'кг', 0.001000, 0, 3);");