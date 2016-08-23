ALTER TABLE shop_order_payee
  CHANGE COLUMN id_author id_author INT(10) UNSIGNED DEFAULT NULL COMMENT 'Идентификатор плательщика',
  ADD COLUMN id_company INT(10) UNSIGNED DEFAULT NULL AFTER id_author;

ALTER TABLE shop_order_payee
  DROP FOREIGN KEY FK_shop_order_payee_se_user_id;

ALTER TABLE shop_order_payee
  ADD CONSTRAINT FK_shop_order_payee_se_user_id FOREIGN KEY (id_author)
    REFERENCES se_user(id) ON DELETE RESTRICT ON UPDATE RESTRICT;

ALTER TABLE shop_order_payee
  ADD CONSTRAINT FK_shop_order_payee_company_id FOREIGN KEY (id_company)
    REFERENCES company(id) ON DELETE RESTRICT ON UPDATE RESTRICT;

ALTER TABLE se_user_account
  ADD COLUMN company_id INT(10) UNSIGNED DEFAULT NULL AFTER user_id;

ALTER TABLE se_user_account
  ADD CONSTRAINT FK_se_user_account_company_id FOREIGN KEY (company_id)
    REFERENCES company(id) ON DELETE RESTRICT ON UPDATE RESTRICT;