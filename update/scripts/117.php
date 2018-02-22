<?php

se_db_query("ALTER TABLE se_group
  ADD COLUMN email_settings VARCHAR(255) DEFAULT NULL COMMENT 'Настройки для email рассылок' AFTER id_parent;");