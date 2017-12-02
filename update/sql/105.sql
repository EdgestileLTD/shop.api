UPDATE shop_order set id_admin = NULL WHERE id_admin < 1;

ALTER TABLE shop_order
  CHANGE COLUMN id_admin id_admin INT(10) NOT NULL;

ALTER TABLE shop_order
  ADD CONSTRAINT FK_shop_order_id_admin FOREIGN KEY (id_admin)
    REFERENCES se_user(id) ON DELETE SET NULL ON UPDATE CASCADE;