<?php

se_db_query("CREATE TABLE IF NOT EXISTS shop_price_group (
				id int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
				id_price int(10) UNSIGNED NOT NULL,
				id_group int(10) UNSIGNED NOT NULL,
				is_main tinyint(1) NOT NULL DEFAULT 1,
				updated_at timestamp NOT NULL DEFAULT '0000-00-00 00:00:00' ON UPDATE CURRENT_TIMESTAMP,
				created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY (id),
				UNIQUE INDEX UK_shop_price_group (id_price, id_group),
				CONSTRAINT FK_shop_price_group_shop_group_id FOREIGN KEY (id_group)
				REFERENCES shop_group (id) ON DELETE CASCADE ON UPDATE CASCADE,
				CONSTRAINT FK_shop_price_group_shop_price_id FOREIGN KEY (id_price)
				REFERENCES shop_price (id) ON DELETE CASCADE ON UPDATE CASCADE
				)
				ENGINE = INNODB
				CHARACTER SET utf8
				COLLATE utf8_general_ci;");

se_db_query("CREATE TABLE IF NOT EXISTS shop_group_tree (
				id int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
				id_parent int(10) UNSIGNED NOT NULL,
				id_child int(10) UNSIGNED NOT NULL,
				level tinyint(4) NOT NULL,
				updated_at timestamp NOT NULL DEFAULT '0000-00-00 00:00:00' ON UPDATE CURRENT_TIMESTAMP,
				created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY (id),
				UNIQUE INDEX UK_shop_group_tree (id_parent, id_child),
				CONSTRAINT FK_shop_group_tree_shop_group_id FOREIGN KEY (id_child)
				REFERENCES shop_group (id) ON DELETE CASCADE ON UPDATE CASCADE,
				CONSTRAINT FK_shop_group_tree_shop_group_tree_id_parent FOREIGN KEY (id_parent)
				REFERENCES shop_group (id) ON DELETE CASCADE ON UPDATE RESTRICT
				)
				ENGINE = INNODB
				CHARACTER SET utf8
				COLLATE utf8_general_ci;");

$u = new seTable("shop_group_tree", "sgt");
$u->select("sgt.id");
$result = $u->getList();
if (!count($result)) {
    se_db_query("INSERT IGNORE INTO shop_price_group (id_price, id_group, is_main)
                  SELECT sp.id, sp.id_group, 1
                  FROM shop_price AS sp
                    INNER JOIN shop_group sg
                      ON sp.id_group = sg.id;");
    se_db_query("INSERT IGNORE INTO shop_price_group (id_price, id_group, is_main)
                  SELECT sp.id, scg.id, 0
                  FROM shop_price AS sp
                    INNER JOIN shop_crossgroup AS scg
                      ON sp.id_group = scg.group_id
                    INNER JOIN shop_group AS sg
                      ON scg.id = sg.id
                      OR scg.group_id = sg.id");
    se_db_query("INSERT IGNORE INTO shop_price_group (id_price, id_group, is_main)
                  SELECT sgp.price_id, sgp.group_id, 0
                  FROM shop_group_price sgp
                    INNER JOIN shop_group AS sg
                      ON sgp.group_id = sg.id
                    INNER JOIN shop_price AS sp
                      ON sgp.price_id = sp.id");
}