<?php

se_db_query('ALTER TABLE news
  ADD COLUMN url VARCHAR(255) DEFAULT NULL AFTER send_letter');

se_db_query('ALTER TABLE news
  ADD COLUMN sort INT(11) DEFAULT NULL AFTER url');

se_db_query('ALTER TABLE news
  ADD COLUMN is_date_public BOOL DEFAULT NULL AFTER sort');