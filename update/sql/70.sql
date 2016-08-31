ALTER TABLE se_user
  ADD COLUMN id_company INT UNSIGNED DEFAULT NULL COMMENT 'компания пользователя (таблица - company)' AFTER last_login;

ALTER TABLE se_user
  ADD CONSTRAINT FK_se_user_company_id FOREIGN KEY (id_company)
    REFERENCES company(id) ON DELETE CASCADE ON UPDATE RESTRICT;