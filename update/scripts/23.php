<?php

    se_db_query("ALTER TABLE news
      CHANGE COLUMN img img VARCHAR(255) DEFAULT NULL;");