<?php

se_db_query("ALTER TABLE main
  ADD COLUMN sms_sender VARCHAR(255) DEFAULT NULL COMMENT 'Отправитель SMS по умолчанию' AFTER sms_phone;");

se_db_query("ALTER TABLE sms_templates
  ADD COLUMN phone VARCHAR(255) DEFAULT NULL COMMENT 'Телефоны получателя по умолчанию' AFTER is_active,
  ADD COLUMN sender VARCHAR(255) DEFAULT NULL AFTER phone;");