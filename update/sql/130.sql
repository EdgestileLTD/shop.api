ALTER TABLE shop_order
  ADD COLUMN id_company int(10) UNSIGNED DEFAULT NULL AFTER id_author;