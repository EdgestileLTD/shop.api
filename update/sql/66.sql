CREATE TABLE company (
  id int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  reg_date datetime DEFAULT CURRENT_TIMESTAMP,
  name varchar(255) NOT NULL,
  fullname varchar(255) DEFAULT NULL,
  inn varchar(50) DEFAULT NULL,
  phone varchar(255) DEFAULT NULL,
  email varchar(255) DEFAULT NULL,
  address varchar(255) DEFAULT NULL,
  note varchar(255) DEFAULT NULL,
  updated_at timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  created_at timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
)
ENGINE = INNODB
CHARACTER SET utf8
COLLATE utf8_general_ci;

ALTER TABLE shop_order
  ADD COLUMN id_company INT(10) UNSIGNED DEFAULT NULL AFTER id_author;

ALTER TABLE shop_order
  ADD CONSTRAINT FK_shop_order_company_id FOREIGN KEY (id_company)
    REFERENCES company(id) ON DELETE SET NULL ON UPDATE RESTRICT;

CREATE TABLE company_person (
  id int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  id_company int(10) UNSIGNED DEFAULT NULL,
  id_person int(10) UNSIGNED DEFAULT NULL,
  updated_at timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  created_at timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
)
ENGINE = INNODB
AVG_ROW_LENGTH = 4096
CHARACTER SET utf8
COLLATE utf8_general_ci;

ALTER TABLE se_user_group
  ADD COLUMN company_id INT(10) UNSIGNED DEFAULT NULL AFTER user_id;

ALTER TABLE se_user_group
  ADD CONSTRAINT FK_se_user_group_company_id FOREIGN KEY (company_id)
    REFERENCES company(id) ON DELETE CASCADE ON UPDATE RESTRICT;