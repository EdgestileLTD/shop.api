<?php

se_db_query("ALTER TABLE shop_group
  ADD CONSTRAINT FK_shop_group_shop_group_id FOREIGN KEY (upid)
    REFERENCES shop_group(id) ON DELETE CASCADE ON UPDATE RESTRICT;");