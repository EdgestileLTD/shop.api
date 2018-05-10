ALTER TABLE shop_feature
  ADD COLUMN `code` VARCHAR(255) DEFAULT NULL AFTER name;

ALTER TABLE shop_feature
  ADD UNIQUE INDEX UK_shop_feature_code (`code`);

ALTER TABLE shop_feature_value_list
  ADD COLUMN `code` VARCHAR(255) DEFAULT NULL AFTER value;

ALTER TABLE shop_feature_value_list
  ADD UNIQUE INDEX UK_shop_feature_value_list_cod (`code`);