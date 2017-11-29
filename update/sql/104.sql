ALTER TABLE shop_group
  CHANGE COLUMN bread_crumb page_title VARCHAR(255) DEFAULT NULL;
ALTER TABLE shop_price
  CHANGE COLUMN bread_crumb page_title VARCHAR(255) DEFAULT NULL;