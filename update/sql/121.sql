ALTER TABLE shop_price
  ADD COLUMN is_show_feature TINYINT(1) NOT NULL DEFAULT 1 AFTER enabled;