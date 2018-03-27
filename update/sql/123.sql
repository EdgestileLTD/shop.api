ALTER TABLE shop_order
  CHANGE COLUMN status status ENUM('Y','N','K','P','W','T','A','D') NOT NULL DEFAULT 'N';