ALTER TABLE shop_deliverytype 
  CHANGE COLUMN time time VARCHAR(10) NOT NULL;
  
ALTER TABLE shop_delivery_param 
  ADD COLUMN name VARCHAR(255) DEFAULT NULL AFTER id_delivery;
  
