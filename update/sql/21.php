<?php

    $u = new seTable('shop_deliverytype','sd');
    if (!$u->isFindField("sort"))
        se_db_query("ALTER TABLE shop_deliverytype
              ADD COLUMN sort INT(11) UNSIGNED NOT NULL DEFAULT 0 AFTER status;");
