DELETE FROM shop_price_measure WHERE NOT id_price IN (SELECT id FROM shop_price);
ALTER TABLE shop_price_measure
  ADD CONSTRAINT FK_shop_price_measure_id_price FOREIGN KEY (id_price)
    REFERENCES shop_price(id) ON DELETE CASCADE ON UPDATE CASCADE;