<?php

//se_db_add_field('shop_group', 'visits', 'int(10) unsigned NOT NULL default 0 AFTER `active`');
se_db_add_field('shop_group', 'picture_alt', 'varchar(255) default NULL AFTER `picture`');
se_db_add_field('shop_group', 'keywords', 'varchar(255) default NULL AFTER `commentary`');
se_db_add_field('shop_group', 'title', 'varchar(255) default NULL AFTER `commentary`');
se_db_add_field('shop_group', 'footertext', 'text default NULL AFTER `commentary`');
se_db_add_field('shop_group', 'description', 'text default NULL AFTER `keywords`');

se_db_add_field('shop_price', 'price_opt_corp', 'double(10,2) AFTER `price_opt`');
se_db_add_field('shop_price', 'description', "text DEFAULT NULL AFTER `keywords`");
se_db_query("ALTER TABLE  `shop_price` CHANGE  `presence_count`  `presence_count` DOUBLE( 10, 3 ) NULL DEFAULT  '-1.000'");
if (!se_db_is_field('shop_price','step_count')){ 
    se_db_add_field('shop_price', 'step_count', "double(10,3) NOT NULL default 1.00 AFTER `presence_count`");
}

se_db_add_field('shop_comm', 'is_active', "enum('Y','N') default 'N' AFTER `mark`");
se_db_add_index('shop_comm', 'is_active', 1); 
se_db_add_field('shop_comm', 'showing', "enum('Y','N') default 'N' AFTER `mark`");
se_db_add_index('shop_comm', 'showing', 1);
se_db_add_field('shop_comm', 'response', 'text default NULL AFTER `commentary`');

// SHOP_ORDER
/*
se_db_add_field('shop_order', 'manager_id', "int unsigned default NULL AFTER `id`");
se_db_add_index('shop_order', 'manager_id', 1);
*/
se_db_query("ALTER TABLE`shop_order` CHANGE `delivery_status` `delivery_status` enum('N', 'Y', 'P', 'M') DEFAULT 'N'");
if (!se_db_is_field('shop_order','is_delete')){ 
      se_db_query("ALTER TABLE`shop_order` CHANGE `is_delete` `is_delete` enum('N', 'Y') DEFAULT 'N'");
      se_db_query("ALTER TABLE `shop_order` ADD INDEX (`is_delete`)");
      se_db_query("UPDATE `shop_order` SET `is_delete`='N' WHERE `is_delete` IS NULL");
}
se_db_query("ALTER TABLE  `shop_order` CHANGE  `status`  `status` ENUM(  'Y',  'N',  'K',  'P',  'W', 'T' ) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT  'N'");
se_db_query("ALTER TABLE  `shop_order` CHANGE  `date_credit`  `date_credit` DATETIME NULL DEFAULT NULL");

// SHOP_DELIVERYTYPE
se_db_add_field('shop_deliverytype', 'code', "varchar(20) NULL AFTER `id`");
se_db_add_index('shop_deliverytype', 'code', 1);
se_db_add_field('shop_deliverytype','city_from_delivery', "VARCHAR( 128 ) NULL AFTER  `forone`");

se_db_add_field('shop_delivery', 'name_recipient', "varchar(150) NOT NULL default '' AFTER `id`");

// MAIN
se_db_add_field('main', 'city_from_delivery', "varchar(40) NULL AFTER `domain`");
se_db_add_field('main', 'domain', 'varchar(255) default NULL AFTER `basecurr`');
se_db_add_field('main', 'shopname', "varchar(255) default NULL AFTER `lang`");
se_db_add_field('main', 'shopname', "VARCHAR( 255 ) NOT NULL AFTER  `lang`");
se_db_add_field('main', 'subname'," VARCHAR( 255 ) NOT NULL AFTER  `shopname`");
se_db_add_field('main', 'logo',"VARCHAR( 255 ) NOT NULL AFTER  `subname`");
se_db_add_field('main', 'is_store',"tinyint(1) NOT NULL DEFAULT 0 AFTER  `domain`");
se_db_add_field('main', 'is_pickup',"tinyint(1) NOT NULL DEFAULT 0 AFTER  `is_store`");
se_db_add_field('main', 'is_delivery',"tinyint(1) NOT NULL DEFAULT 1 AFTER  `is_pickup`");
se_db_add_field('main', 'local_delivery_cost',"DOUBLE NOT NULL DEFAULT  '0.00' AFTER  `is_delivery`");


se_db_query("
   ALTER TABLE `session` CHANGE `TIMES` `TIMES` INT( 11 ) NULL DEFAULT NULL;
");

if(!function_exists('se_db_is_index') ||!se_db_is_index('session', 'SID')){
    se_db_query("ALTER TABLE `session` ADD PRIMARY KEY ( `SID` )");
}

se_db_add_index('session', 'TIMES', 1);
se_db_add_index('session', 'IDUSER', 1);
se_db_add_index('session', 'GROUPUSER', 1);
se_db_add_index("session", 'IP', 1);


se_db_add_field('shop_price', 'is_market', "BOOLEAN NOT NULL DEFAULT FALSE AFTER  `vizits`");
se_db_add_field('shop_price', 'is_market', 1);


// SHOP_MAIL
se_db_add_field('shop_mail', 'shop_mail_group_id', 'int(10) unsigned default 1 AFTER `id`');
se_db_query('UPDATE shop_mail SET shop_mail_group_id=1 WHERE shop_mail_group_id IS NULL');

/*
// SHOP_DISCOUNT
if (!se_db_is_field('shop_discount','id_user')){ 
    se_db_add_field('shop_discount', 'id_user', "int unsigned default NULL AFTER `id_group`");
    se_db_query("ALTER TABLE `shop_discount` ADD INDEX (`id_user`)");
}
if (!se_db_is_field('shop_discount','priority')){ 
    se_db_add_field('shop_discount', 'priority', "smallint unsigned default NULL AFTER `id_user`");
}
se_db_query("ALTER TABLE`shop_discount` CHANGE `type` `type` enum('g','p','o','m','i') NOT NULL default 'm'");
*/

// SHOP_PAYMENT
se_db_add_field('shop_payment', 'url_help', "varchar(255) default NULL AFTER `authorize`");
se_db_add_field('shop_payment', 'ident', "varchar(40) default NULL AFTER `lang`");
se_db_add_field('shop_payment', 'is_test', "enum('Y','N') default 'N' AFTER `active`");
se_db_query("ALTER TABLE  `shop_payment` CHANGE  `blank`  `blank` MEDIUMTEXT CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL");
se_db_query("ALTER TABLE  `shop_payment` CHANGE  `result`  `result` MEDIUMTEXT CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL");
se_db_add_field('shop_payment', 'way_payment', "enum('b','a') NOT NULL default 'b' AFTER `authorize`");
se_db_add_index('shop_payment', 'way_payment', 1);
se_db_query("ALTER TABLE  `shop_payment` CHANGE  `active`  `active` ENUM(  'N',  'Y',  'T' ) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT  'N'");


// SHOP_DELIVERY_PAYMENT
se_db_query("CREATE TABLE IF NOT EXISTS `shop_delivery_payment` (
`id` int(10) unsigned NOT NULL AUTO_INCREMENT,
`id_delivery` int(10) unsigned NOT NULL,
`id_payment` int(10) unsigned NOT NULL,
`updated_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00' ON UPDATE CURRENT_TIMESTAMP,
`created_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
PRIMARY KEY (`id`),
UNIQUE KEY `del_pay_index` (`id_delivery`,`id_payment`),
KEY `id_delivery` (`id_delivery`),
KEY `id_payment` (`id_payment`),
CONSTRAINT `shop_delivery_payment_ibfk_1` FOREIGN KEY (`id_delivery`) REFERENCES `shop_deliverytype` (`id`) ON DELETE CASCADE,
CONSTRAINT `shop_delivery_payment_ibfk_2` FOREIGN KEY (`id_payment`) REFERENCES `shop_payment` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;");

se_db_add_field('person', 'subscriber_news', "enum('Y','N') NOT NULL default 'N' AFTER `loyalty`");
se_db_add_index('person', 'subscriber_news', 1);


se_db_query("
CREATE TABLE IF NOT EXISTS `shop_crossgroup` (
`id` int(10) unsigned NOT NULL,
`group_id` int(10) unsigned DEFAULT NULL,
`updated_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00' ON UPDATE CURRENT_TIMESTAMP,
`created_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
UNIQUE KEY `id_groupid_uni` (`id`,`group_id`),
KEY `id` (`id`),
KEY `group_id` (`group_id`),
CONSTRAINT `shop_crossgroup_ibfk_2` FOREIGN KEY (`group_id`) REFERENCES `shop_group` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
CONSTRAINT `shop_crossgroup_ibfk_1` FOREIGN KEY (`id`) REFERENCES `shop_group` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;");


