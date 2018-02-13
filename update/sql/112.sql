ALTER TABLE shop_accomp
  CHANGE COLUMN id_acc id_acc INT(10) UNSIGNED DEFAULT NULL,
  ADD COLUMN id_group INT(10) UNSIGNED DEFAULT NULL AFTER id_acc;

ALTER TABLE shop_accomp
  ADD CONSTRAINT FK_shop_accomp_id_group FOREIGN KEY (id_group)
    REFERENCES shop_group(id) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE shop_accomp
  DROP FOREIGN KEY FK_shop_accomp_id_group;

ALTER TABLE shop_accomp
  ADD CONSTRAINT FK_shop_accomp_id_group FOREIGN KEY (id_group)
    REFERENCES shop_group(id) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE shop_accomp
  CHANGE COLUMN id_group id_group INT(10) UNSIGNED DEFAULT NULL;

ALTER TABLE shop_accomp
  DROP INDEX uprice,
  ADD UNIQUE INDEX uprice (id_price, id_acc, id_group);

ALTER TABLE shop_accomp
  ADD CONSTRAINT FK_shop_accomp_id_price FOREIGN KEY (id_price)
    REFERENCES shop_price(id) ON DELETE CASCADE ON UPDATE CASCADE;