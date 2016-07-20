<?php

se_db_query("UPDATE main SET basecurr = 'RUB' WHERE basecurr = 'RUR'");
se_db_query("UPDATE shop_price sp SET sp.curr = 'RUB' WHERE sp.curr = 'RUR'");
se_db_query("UPDATE shop_order so SET so.curr = 'RUB' WHERE so.curr = 'RUR'");
se_db_query("ALTER TABLE shop_order CHANGE COLUMN curr curr VARCHAR(3) NOT NULL DEFAULT 'RUB';");
se_db_query("ALTER TABLE shop_price CHANGE COLUMN curr curr CHAR(3) NOT NULL DEFAULT 'RUB';");
se_db_query("ALTER TABLE main CHANGE COLUMN basecurr basecurr CHAR(3) DEFAULT 'RUB';");