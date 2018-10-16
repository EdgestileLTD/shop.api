ALTER TABLE shop_order_payee
  ADD COLUMN curr CHAR(3) DEFAULT 'RUB' AFTER amount;