<?php

se_db_query("ALTER TABLE shop_group_related
  CHANGE COLUMN type type TINYINT(2) NOT NULL DEFAULT 1 COMMENT '1 - похожий, 2 - сопуствующий, 3 - доп. подгруппа';");