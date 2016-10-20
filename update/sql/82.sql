ALTER TABLE shop_discounts
  ADD COLUMN week_product BOOL NOT NULL DEFAULT 0 AFTER customer_type;