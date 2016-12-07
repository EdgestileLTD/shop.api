ALTER TABLE person
  ADD COLUMN price_type SMALLINT(6) UNSIGNED DEFAULT 0 AFTER referer,
  COMMENT = 'Тип цены: 0 - розничная, 1 - мелкооптовая, 2 - оптовая';