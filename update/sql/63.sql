CREATE TABLE sms_providers (
  id int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  name varchar(50) DEFAULT NULL COMMENT 'Наименование шлюза',
  settings varchar(255) NOT NULL COMMENT 'настройки СМС шлюза (JSON формат)',
  is_active tinyint(1) DEFAULT 0,
  updated_at timestamp NOT NULL DEFAULT '0000-00-00 00:00:00' ON UPDATE CURRENT_TIMESTAMP,
  created_at timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
)
ENGINE = INNODB
AVG_ROW_LENGTH = 8192
CHARACTER SET utf8
COLLATE utf8_general_ci;

CREATE TABLE sms_templates (
  id int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  code varchar(50) DEFAULT NULL,
  name varchar(50) DEFAULT NULL,
  text varchar(255) DEFAULT NULL,
  is_active tinyint(1) DEFAULT 1,
  updated_at timestamp NOT NULL DEFAULT '0000-00-00 00:00:00' ON UPDATE CURRENT_TIMESTAMP,
  created_at timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
)
ENGINE = INNODB
AVG_ROW_LENGTH = 16384
CHARACTER SET utf8
COLLATE utf8_general_ci;

INSERT INTO sms_providers(id, name, settings, is_active) VALUES
(1, 'sms.ru', '{"api_id":{"type":"string","value":""}}', 1);
INSERT INTO sms_providers(id, name, settings, is_active) VALUES
(2, 'qtelecom.ru', '{"login":{"type":"string","value":""},"password":{"type":"string","value":""}}', 0);
INSERT INTO sms_templates(id, code, name, text, is_active) VALUES
(1, 'orderadm', 'SMS администратору о заказе', 'Оформлен заказ №[SHOP_ORDER_NUM]. Сумма:[SHOP_ORDER_SUMM] Сумма доставки:[SHOP_ORDER_DEVILERY]', 1);