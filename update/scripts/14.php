<?php
    se_db_add_field('main', 'folder', "varchar(255) default NULL");
    se_db_add_index('main', 'folder', 2);


    se_db_add_field('shop_group', 'id_main', "int(10) unsigned default 1");
    se_db_add_index('shop_group', 'id_main', 1);

    se_db_add_field('shop_order', 'id_main', "int(10) unsigned default 1");
    se_db_add_index('shop_order', 'id_main', 1);
