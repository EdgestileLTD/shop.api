<?php

se_db_query("ALTER TABLE company
  ADD COLUMN kpp VARCHAR(32) DEFAULT NULL COMMENT 'код постановки на учет в налоговом органе' AFTER inn;");