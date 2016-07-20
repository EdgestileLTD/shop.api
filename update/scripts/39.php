<?php

se_db_query("ALTER TABLE main
  ADD COLUMN time_modified INT(11) UNSIGNED DEFAULT NULL AFTER is_manual_curr_rate;");