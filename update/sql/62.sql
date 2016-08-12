ALTER TABLE main
  ADD COLUMN sms_phone VARCHAR(255) DEFAULT NULL COMMENT 'Телефон для СМС информирование' AFTER folder;