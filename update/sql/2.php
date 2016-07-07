<?php

    // обновление таблицы shop_price
    $u = new seTable('shop_price','sp');
    if (!$u->isFindField("is_market"))
        $u->addField("is_market", "BOOL", 1);