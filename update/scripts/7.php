<?php
se_db_query("
CREATE TABLE IF NOT EXISTS `shop_coupons` (
`id` int(10) unsigned NOT NULL AUTO_INCREMENT,
`code` varchar(50) NOT NULL,
`type` enum('p','a') NOT NULL DEFAULT 'p',
`discount` float(10,2) DEFAULT NULL,
`currency` char(3) NOT NULL DEFAULT 'RUR',
`expire_date` date DEFAULT NULL,
`min_sum_order` float(10,2) DEFAULT NULL,
`status` enum('Y','N') NOT NULL DEFAULT 'Y',
`count_used` int(10) unsigned NOT NULL DEFAULT '1',
`payment_id` int(10) unsigned DEFAULT NULL,
`only_registered` enum('Y','N') NOT NULL DEFAULT 'N',
`updated_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00' ON UPDATE CURRENT_TIMESTAMP,
`created_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
PRIMARY KEY (`id`),
UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;
");

se_db_query("
CREATE TABLE IF NOT EXISTS `shop_delivery_param` (
`id` int(10) unsigned NOT NULL AUTO_INCREMENT,
`id_delivery` int(10) unsigned NOT NULL,
`type_param` enum('sum','weight','volume') DEFAULT 'sum',
`price` double(16,2) unsigned NOT NULL,
`min_value` double(16,3) DEFAULT NULL,
`max_value` double(16,3) DEFAULT NULL,
`priority` int(11) DEFAULT '0',
`operation` enum('=','+','-') DEFAULT '=',
`type_price` enum('a','s','d') DEFAULT 'a',
`updated_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00' ON UPDATE CURRENT_TIMESTAMP,
`created_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
PRIMARY KEY (`id`),
KEY `id_delivery` (`id_delivery`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;
");

se_db_query("
CREATE TABLE IF NOT EXISTS `shop_delivery_region` (
`id` int(10) unsigned NOT NULL AUTO_INCREMENT,
`id_delivery` int(10) unsigned NOT NULL,
`id_country` int(11) DEFAULT NULL,
`id_region` int(11) DEFAULT NULL,
`id_city` int(11) DEFAULT NULL,
`updated_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00' ON UPDATE CURRENT_TIMESTAMP,
`created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
PRIMARY KEY (`id`),
KEY `id_delivery` (`id_delivery`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;
");

se_db_query("
CREATE TABLE IF NOT EXISTS `shop_delivery_payment` (
`id` int(11) NOT NULL AUTO_INCREMENT,
`id_delivery` int(10) unsigned NOT NULL,
`id_payment` int(10) unsigned NOT NULL,
`updated_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00' ON UPDATE CURRENT_TIMESTAMP,
`created_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
PRIMARY KEY (`id`),
UNIQUE KEY `untypegroup` (`id_payment`,`id_delivery`)
) ENGINE=InnoDB AUTO_INCREMENT=64 DEFAULT CHARSET=utf8;
");

se_db_query("
CREATE TABLE IF NOT EXISTS `shop_coupons_goods` (
`id` int(10) unsigned NOT NULL AUTO_INCREMENT,
`coupon_id` int(10) unsigned NOT NULL,
`group_id` int(10) unsigned DEFAULT NULL,
`price_id` int(10) unsigned DEFAULT NULL,
`updated_at` timestamp NULL DEFAULT NULL,
`created_at` timestamp NULL DEFAULT NULL,
 PRIMARY KEY (`id`),
UNIQUE KEY `idkey` (`coupon_id`,`group_id`,`price_id`),
KEY `group_id` (`group_id`),
KEY `price_id` (`price_id`),
KEY `coupon_id` (`coupon_id`),
CONSTRAINT `shop_coupons_goods_ibfk_1` FOREIGN KEY (`coupon_id`) REFERENCES `shop_coupons` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB  DEFAULT CHARSET=utf8;
");


se_db_query("
CREATE TABLE IF NOT EXISTS `news_img` (
`id` int(10) unsigned NOT NULL AUTO_INCREMENT,
`id_news` int(10) unsigned NOT NULL,
`picture` varchar(255) DEFAULT NULL,
`picture_alt` varchar(255) DEFAULT NULL,
`title` varchar(255) DEFAULT NULL,
`sort` int(11) NOT NULL DEFAULT '0',
`updated_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00' ON UPDATE CURRENT_TIMESTAMP,
`created_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
PRIMARY KEY (`id`),
KEY `id_news` (`id_news`),
CONSTRAINT `news_img_ibfk_2` FOREIGN KEY (`id_news`) REFERENCES `news` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;");

se_db_query("
CREATE TABLE IF NOT EXISTS `shop_group_img` (
`id` int(10) unsigned NOT NULL AUTO_INCREMENT,
`id_group` int(10) unsigned NOT NULL,
`picture` varchar(255) DEFAULT NULL,
`picture_alt` varchar(255) DEFAULT NULL,
`title` varchar(255) DEFAULT NULL,
`sort` int(11) NOT NULL DEFAULT '0',
`updated_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00' ON UPDATE CURRENT_TIMESTAMP,
`created_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
PRIMARY KEY (`id`),
KEY `id_group` (`id_group`),
CONSTRAINT `group_img_ibfk_2` FOREIGN KEY (`id_group`) REFERENCES `shop_group` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;");

se_db_query("
CREATE TABLE IF NOT EXISTS `shop_discounts` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `action` enum('single','constant','increase','falling') NOT NULL DEFAULT 'single',
  `step_time` int(10) unsigned NOT NULL DEFAULT '0',
  `step_discount` double(10,3) NOT NULL DEFAULT '0.000',
  `date_from` varchar(19) DEFAULT NULL,
  `date_to` varchar(19) DEFAULT NULL,
  `summ_from` double(10,2) DEFAULT NULL,
  `summ_to` double(10,2) DEFAULT NULL,
  `count_from` int(11) DEFAULT '-1',
  `count_to` int(11) DEFAULT '-1',
  `discount` double(10,3) DEFAULT '5.000',
  `type_discount` enum('percent','absolute') NOT NULL DEFAULT 'percent',
  `week` char(7) DEFAULT NULL,
  `summ_type` int(10) unsigned DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00' ON UPDATE CURRENT_TIMESTAMP,
  `created_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY (`id`),
  KEY `updated_at` (`updated_at`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;");

se_db_query("
CREATE TABLE IF NOT EXISTS `shop_discount_links` (
 `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
`discount_id` int(10) unsigned NOT NULL,
`id_price` int(10) unsigned DEFAULT NULL,
`id_group` int(10) unsigned DEFAULT NULL,
`id_user` int(10) unsigned DEFAULT NULL,
`priority` smallint(5) unsigned DEFAULT NULL,
`type` enum('g','p','o','m','i') NOT NULL DEFAULT 'm',
`updated_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00' ON UPDATE CURRENT_TIMESTAMP,
`created_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
PRIMARY KEY (`id`),
KEY `id_price` (`id_price`),
KEY `id_group` (`id_group`),
KEY `id_user` (`id_user`),
KEY `updated_at` (`updated_at`),
KEY `discount_id` (`discount_id`),
CONSTRAINT `shop_discount_links_ibfk_1` FOREIGN KEY (`discount_id`) REFERENCES `shop_discounts` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;
");


se_db_add_field('shop_coupons', 'payment_id', "int(10) unsigned DEFAULT NULL AFTER `count_used`");
se_db_add_field('shop_coupons', 'only_registered', "enum('Y','N') NOT NULL DEFAULT 'N' AFTER `count_used`");


se_db_add_field('shop_deliverytype', 'time_to', "time DEFAULT NULL AFTER `note`");
se_db_add_field('shop_deliverytype', 'time_from', "time DEFAULT NULL AFTER `note`");
se_db_add_field('shop_deliverytype', 'week', "char(7) DEFAULT '1111111' AFTER `note`");
se_db_add_field('shop_deliverytype', 'max_weight', "float(10,3) unsigned DEFAULT NULL AFTER `note`");
se_db_add_field('shop_deliverytype', 'max_volume', "int(11) unsigned DEFAULT NULL AFTER `note`");
se_db_add_field('shop_deliverytype', 'status', "enum('Y','N') DEFAULT 'Y' AFTER `forone`");
se_db_add_field('shop_deliverytype', 'need_address', "enum('Y','N') DEFAULT 'Y' AFTER `note`");

if (!se_db_is_field('shop_coupons_goods','coupon_id')){
    se_db_add_field('shop_coupons_goods', 'coupon_id', "int(10) unsigned NOT NULL AFTER `id`");
    se_db_query('ALTER TABLE `shop_coupons_goods` DROP FOREIGN KEY  `shop_coupons_goods_ibfk_1`;');
    se_db_query('ALTER TABLE `shop_coupons_goods` ADD INDEX (`coupon_id`);');
    se_db_query("ALTER TABLE `shop_coupons_goods` ADD CONSTRAINT `shop_coupons_goods_ibfk_1` FOREIGN KEY (`coupon_id`) REFERENCES `shop_coupons` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;");
}

se_db_query("
CREATE TABLE IF NOT EXISTS `shop_coupons_history` (
`id` int(10) unsigned NOT NULL AUTO_INCREMENT,
`code_coupon` varchar(50) NOT NULL,
`id_coupon` int(10) unsigned NOT NULL,
`id_user` int(10) unsigned DEFAULT NULL,
`id_order` int(10) unsigned NOT NULL,
`discount` float(10,2) DEFAULT NULL,
`updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
`created_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
PRIMARY KEY (`id`),
KEY `id_coupon` (`id_coupon`),
KEY `id_user` (`id_user`),
KEY `id_order` (`id_order`),
CONSTRAINT `shop_coupons_history_fk` FOREIGN KEY (`id_coupon`) REFERENCES `shop_coupons` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
CONSTRAINT `shop_coupons_history_fk1` FOREIGN KEY (`id_order`) REFERENCES `shop_order` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;
");

// АFEATURE
se_db_query("CREATE TABLE IF NOT EXISTS `shop_feature_group` (
id int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
id_main int(10) UNSIGNED NOT NULL DEFAULT 1,
name varchar(255) NOT NULL,
description text DEFAULT NULL,
image varchar(255) DEFAULT NULL,
sort int(10) NOT NULL DEFAULT 0,
updated_at timestamp DEFAULT '0000-00-00 00:00:00' ON UPDATE CURRENT_TIMESTAMP,
created_at timestamp DEFAULT '0000-00-00 00:00:00',
PRIMARY KEY (id),
INDEX id_main (id_main),
INDEX sort (sort)
)
ENGINE = INNODB
AUTO_INCREMENT = 1
AVG_ROW_LENGTH = 5461
CHARACTER SET utf8
COLLATE utf8_general_ci;");

se_db_query("CREATE TABLE IF NOT EXISTS `shop_modifications_group` (
`id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
`id_main` int(10) UNSIGNED NOT NULL DEFAULT 1,
`name` varchar(50) NOT NULL,
`vtype` smallint(1) UNSIGNED DEFAULT 0,
`sort` int(10) DEFAULT 0,
`updated_at` timestamp DEFAULT '0000-00-00 00:00:00' ON UPDATE CURRENT_TIMESTAMP,
`created_at` timestamp DEFAULT '0000-00-00 00:00:00',
PRIMARY KEY (id),
INDEX id_main(`id_main`),
INDEX sort(`sort`),
INDEX updated_at(`updated_at`)
)
ENGINE = INNODB
AUTO_INCREMENT = 1
CHARACTER SET utf8
COLLATE utf8_general_ci;");

se_db_query("CREATE TABLE IF NOT EXISTS `shop_feature` (
`id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
`id_feature_group` int(10) UNSIGNED DEFAULT NULL,
`name` varchar(255) NOT NULL,
`type` enum ('list', 'colorlist', 'number', 'bool', 'string') DEFAULT 'list',
`image` varchar(255) DEFAULT NULL,
`measure` varchar(20) DEFAULT NULL,
`description` text DEFAULT NULL,
`sort` int(10) NOT NULL DEFAULT 0,
`updated_at` timestamp DEFAULT '0000-00-00 00:00:00' ON UPDATE CURRENT_TIMESTAMP,
`created_at` timestamp DEFAULT '0000-00-00 00:00:00',
PRIMARY KEY (id),
INDEX id_mod_group (id_feature_group),
CONSTRAINT shop_feature_ibfk_1 FOREIGN KEY (id_feature_group)
REFERENCES shop_feature_group (id) ON DELETE CASCADE ON UPDATE CASCADE
)
ENGINE = INNODB
AUTO_INCREMENT = 1
CHARACTER SET utf8
COLLATE utf8_general_ci;");


se_db_query("CREATE TABLE IF NOT EXISTS shop_modifications (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_mod_group` int(10) unsigned NOT NULL,
  `id_price` int(10) unsigned NOT NULL,
  `code` varchar(40) default NULL,
  `value` double(10,2) NOT NULL,
  `value_opt` double(10,2) NOT NULL,
  `value_opt_corp` double(10,2) NOT NULL,
  `count` int(10) DEFAULT NULL,
  `sort` int(10) NOT NULL DEFAULT '0',
  `default` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `id_exchange` varchar(40) DEFAULT NULL,
  `description` TEXT NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00' ON UPDATE CURRENT_TIMESTAMP,
  `created_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY (`id`),
  KEY `id_mod_group` (`id_mod_group`),
  KEY `price_id` (`id_price`),
  KEY `sort` (`sort`),
  KEY `id_exchange` (`id_exchange`),
CONSTRAINT FK_shop_modifications_shop_modifications_group_id FOREIGN KEY (id_mod_group)
REFERENCES shop_modifications_group (id) ON DELETE CASCADE ON UPDATE CASCADE,
CONSTRAINT shop_modifications_ibfk_1 FOREIGN KEY (id_price)
REFERENCES shop_price (id) ON DELETE CASCADE ON UPDATE CASCADE
)
ENGINE = INNODB
AUTO_INCREMENT = 1
CHARACTER SET utf8
COLLATE utf8_general_ci;");


se_db_query("CREATE TABLE IF NOT EXISTS `shop_group_feature` (
id int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
id_group int(10) UNSIGNED NOT NULL,
id_feature int(10) UNSIGNED NOT NULL,
sort int(10) NOT NULL DEFAULT 0,
updated_at timestamp DEFAULT '0000-00-00 00:00:00' ON UPDATE CURRENT_TIMESTAMP,
created_at timestamp DEFAULT '0000-00-00 00:00:00',
PRIMARY KEY (id),
INDEX shop_group_filter_uk2 (id_group),
INDEX sort (sort),
CONSTRAINT FK_shop_group_feature_shop_feature_id FOREIGN KEY (id_feature)
REFERENCES shop_feature (id) ON DELETE CASCADE ON UPDATE CASCADE,
CONSTRAINT FK_shop_group_feature_shop_group_id FOREIGN KEY (id_group)
REFERENCES shop_group (id) ON DELETE CASCADE ON UPDATE CASCADE
)
ENGINE = INNODB
AUTO_INCREMENT = 1
CHARACTER SET utf8
COLLATE utf8_general_ci;");



se_db_query("CREATE TABLE IF NOT EXISTS shop_feature_value_list (
id int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
id_feature int(10) UNSIGNED NOT NULL,
value varchar(255) NOT NULL,
color varchar(6) DEFAULT NULL,
sort int(10) NOT NULL DEFAULT 0,
`default` tinyint(1) NOT NULL DEFAULT 0,
updated_at timestamp DEFAULT '0000-00-00 00:00:00' ON UPDATE CURRENT_TIMESTAMP,
created_at timestamp DEFAULT '0000-00-00 00:00:00',
PRIMARY KEY (id),
CONSTRAINT shop_feature_value_fk1 FOREIGN KEY (id_feature)
REFERENCES shop_feature (id) ON DELETE CASCADE ON UPDATE CASCADE
)
ENGINE = INNODB
AUTO_INCREMENT = 1
CHARACTER SET utf8
COLLATE utf8_general_ci;");


se_db_query("CREATE TABLE IF NOT EXISTS `shop_group_filter` (
id int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
id_group int(10) UNSIGNED NOT NULL,
id_feature int(10) UNSIGNED DEFAULT NULL,
default_filter enum ('price', 'brand', 'flag_hit', 'flag_new', 'discount') DEFAULT NULL,
expanded tinyint(1) DEFAULT 0,
sort int(10) NOT NULL DEFAULT 0,
updated_at timestamp DEFAULT '0000-00-00 00:00:00' ON UPDATE CURRENT_TIMESTAMP,
created_at timestamp DEFAULT '0000-00-00 00:00:00',
PRIMARY KEY (id),
UNIQUE INDEX UK_shop_group_filter (id_feature, id_group),
UNIQUE INDEX UK_shop_group_filter2 (id_group, default_filter),
CONSTRAINT shop_group_filter_fk1 FOREIGN KEY (id_group)
REFERENCES shop_group (id) ON DELETE CASCADE ON UPDATE CASCADE,
CONSTRAINT shop_group_filter_fk2 FOREIGN KEY (id_feature)
REFERENCES shop_feature (id) ON DELETE SET NULL ON UPDATE CASCADE
)
ENGINE = INNODB
AUTO_INCREMENT = 1
CHARACTER SET utf8
COLLATE utf8_general_ci;");

se_db_query("CREATE TABLE IF NOT EXISTS `shop_modifications_img` (
id int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
id_modification int(10) UNSIGNED NOT NULL,
id_img int(10) UNSIGNED NOT NULL,
sort int(11) NOT NULL DEFAULT 0,
updated_at timestamp DEFAULT '0000-00-00 00:00:00' ON UPDATE CURRENT_TIMESTAMP,
created_at timestamp DEFAULT '0000-00-00 00:00:00',
PRIMARY KEY (id),
INDEX id_modification (id_modification),
INDEX sort (sort),
CONSTRAINT FK_shop_modifications_img_shop_img_id FOREIGN KEY (id_img)
REFERENCES shop_img (id) ON DELETE CASCADE ON UPDATE CASCADE,
CONSTRAINT FK_shop_modifications_img_shop_modifications_id FOREIGN KEY (id_modification)
REFERENCES shop_modifications (id) ON DELETE CASCADE ON UPDATE CASCADE
)
ENGINE = INNODB
AUTO_INCREMENT = 1
CHARACTER SET utf8
COLLATE utf8_general_ci;");


se_db_query("CREATE TABLE IF NOT EXISTS `shop_modifications_feature` (
id int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
id_price int(10) UNSIGNED DEFAULT NULL,
id_modification int(10) UNSIGNED DEFAULT NULL,
id_feature int(10) UNSIGNED NOT NULL,
id_value int(10) UNSIGNED DEFAULT NULL,
value_number double DEFAULT NULL,
value_bool tinyint(1) DEFAULT NULL,
value_string varchar(255) DEFAULT NULL,
sort int(10) NOT NULL DEFAULT 0,
updated_at timestamp DEFAULT '0000-00-00 00:00:00' ON UPDATE CURRENT_TIMESTAMP,
created_at timestamp DEFAULT '0000-00-00 00:00:00',
PRIMARY KEY (id),
INDEX id_modification (id_modification),
INDEX id_feature (id_feature),
INDEX id_price (id_price),
INDEX id_value (id_value),
UNIQUE INDEX shop_price_feature_uk1 (id_modification, id_price, id_feature, id_value),
CONSTRAINT FK_shop_modifications_feature_shop_price_id FOREIGN KEY (id_price)
REFERENCES shop_price (id) ON DELETE CASCADE ON UPDATE CASCADE,
CONSTRAINT shop_modifications_feature_ibfk_1 FOREIGN KEY (id_modification)
REFERENCES shop_modifications (id) ON DELETE CASCADE ON UPDATE CASCADE,
CONSTRAINT shop_price_feature_fk2 FOREIGN KEY (id_feature)
REFERENCES shop_feature (id) ON DELETE CASCADE ON UPDATE CASCADE,
CONSTRAINT shop_price_feature_fk3 FOREIGN KEY (id_value)
REFERENCES shop_feature_value_list (id) ON DELETE SET NULL ON UPDATE CASCADE
)
ENGINE = INNODB
AUTO_INCREMENT = 1
CHARACTER SET utf8
COLLATE utf8_general_ci;");


if (!se_db_is_field('shop_modifications','value_opt')){ 
    se_db_add_field('shop_modifications', 'value_opt', "double(10,2) NOT NULL AFTER `value`");
}
if (!se_db_is_field('shop_modifications','value_opt_corp')){ 
    se_db_add_field('shop_modifications', 'value_opt_corp', "double(10,2) NOT NULL AFTER `value_opt`");
}

if (!se_db_is_field('shop_modifications','`default`')){ 
    se_db_query("ALTER TABLE  `shop_modifications` ADD `default` BOOLEAN NOT NULL DEFAULT FALSE AFTER `sort`, ADD INDEX (  `default` )");
}

se_db_query("
CREATE TABLE IF NOT EXISTS `shop_brand` (
`id` int(10) unsigned NOT NULL AUTO_INCREMENT,
`lang` char(3) NOT NULL DEFAULT 'rus',
`name` varchar(255) NOT NULL,
`code` varchar(255) NOT NULL,
`image` varchar(255) DEFAULT NULL,
`text` text,
`title` varchar(255) DEFAULT NULL,
`keywords` varchar(255) DEFAULT NULL,
`description` text,
`updated_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00' ON UPDATE CURRENT_TIMESTAMP,
`created_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
PRIMARY KEY (`id`),
UNIQUE KEY `code_brand` (`code`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;");


if (!se_db_is_field('shop_price','id_brand')){
   se_db_query("ALTER TABLE  `shop_price` CHANGE  `id_manufacturer`  `id_brand` INT( 10 ) UNSIGNED NULL DEFAULT NULL");
}





if (!se_db_is_field('shop_modifications','id_exchange')){ 
    se_db_add_field('shop_modifications', 'id_exchange', "varchar(40) default NULL AFTER `default`");
}

if (!se_db_is_field('shop_modifications','code')){ 
    se_db_add_field('shop_modifications', 'code', "varchar(40) default NULL AFTER `id_price`");
}


if (!se_db_is_field('shop_modifications','description')){ 
    se_db_add_field('shop_modifications', 'description', "TEXT  AFTER `id_exchange`");
}

se_db_query("CREATE TABLE IF NOT EXISTS `shop_rating` (
`id` int(10) unsigned NOT NULL AUTO_INCREMENT,
`id_price` int(10) unsigned NOT NULL,
`id_user` int(10) unsigned NOT NULL,
`mark` smallint(1) unsigned NOT NULL,
`updated_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00' ON UPDATE CURRENT_TIMESTAMP,
`created_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
PRIMARY KEY (`id`),
UNIQUE KEY `UK_shop_rating` (`id_price`,`id_user`),
KEY `FK_shop_rating_se_user_id` (`id_user`),
CONSTRAINT `FK_shop_rating_se_user_id` FOREIGN KEY (`id_user`)
REFERENCES `se_user` (`id`) ON DELETE NO ACTION ON UPDATE CASCADE,
CONSTRAINT `FK_shop_rating_shop_price_id` FOREIGN KEY (`id_price`) 
REFERENCES `shop_price` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;");

se_db_query("ALTER TABLE shop_modifications_feature
  DROP INDEX shop_price_feature_uk1, ADD UNIQUE INDEX shop_price_feature_uk1 (id_modification, id_price, id_feature, id_value);");
  
se_db_add_field('shop_tovarorder', 'modifications', "varchar(255) default NULL AFTER `count`");