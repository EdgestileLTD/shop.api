ALTER TABLE shop_price
  ADD COLUMN market_available TINYINT(1) NOT NULL DEFAULT 1 AFTER market_category;