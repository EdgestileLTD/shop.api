<?php
se_db_query("CREATE TABLE IF NOT EXISTS `shop_stat_session` (
`id` int(10) unsigned NOT NULL AUTO_INCREMENT,
`sid` varchar(32) NOT NULL,
`ip` varchar(15) DEFAULT NULL,
`id_user` int(10) unsigned DEFAULT NULL,
`updated_at` timestamp NULL DEFAULT '0000-00-00 00:00:00',
`created_at` timestamp NULL DEFAULT '0000-00-00 00:00:00',
 UNIQUE KEY `UK_shop_stat_contact` (`id_session`,`contact`,`value`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 AVG_ROW_LENGTH=16384;");



se_db_query("CREATE TABLE IF NOT EXISTS `shop_stat_cart` (
`id_session` int(10) unsigned NOT NULL,
`id_product` int(10) unsigned NOT NULL,
`modifications` varchar(255) DEFAULT NULL,
`count` double(10,3) unsigned DEFAULT NULL,
`updated_at` timestamp NULL DEFAULT '0000-00-00 00:00:00',
`created_at` timestamp NULL DEFAULT '0000-00-00 00:00:00',
 KEY `FK_shop_stat_cart_shop_stat_session_id` (`id_session`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AVG_ROW_LENGTH=5461;");


se_db_query("CREATE TABLE IF NOT EXISTS `shop_stat_contact` (
`id_session` int(10) unsigned NOT NULL,
`contact` varchar(50) NOT NULL,
`value` varchar(255) NOT NULL,
`updated_at` timestamp NULL DEFAULT '0000-00-00 00:00:00',
`created_at` timestamp NULL DEFAULT '0000-00-00 00:00:00',
UNIQUE KEY `UK_shop_stat_contact` (`id_session`,`contact`,`value`),
CONSTRAINT `FK_shop_stat_contact_shop_stat_session_id` FOREIGN KEY (`id_session`) REFERENCES `shop_stat_session` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;");


se_db_query("CREATE TABLE IF NOT EXISTS `shop_stat_events` (
`id_session` int(10) unsigned NOT NULL,
`event` varchar(50) NOT NULL,
`number` smallint(5) unsigned NOT NULL DEFAULT '0',
`content` varchar(100) DEFAULT NULL,
`updated_at` timestamp NULL DEFAULT '0000-00-00 00:00:00',
`created_at` timestamp NULL DEFAULT '0000-00-00 00:00:00',
 KEY `FK_shop_stat_events_shop_stat_session_id` (`id_session`),
 CONSTRAINT `FK_shop_stat_events_shop_stat_session_id` FOREIGN KEY (`id_session`) REFERENCES `shop_stat_session` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
 CONSTRAINT `FK_shop_stat_cart_shop_stat_session_id` FOREIGN KEY (`id_session`) REFERENCES `shop_stat_session` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AVG_ROW_LENGTH=2340;");


se_db_query("CREATE TABLE IF NOT EXISTS `shop_stat_viewgoods` (
`id_session` int(10) unsigned NOT NULL,
`id_product` int(10) NOT NULL,
`updated_at` timestamp NULL DEFAULT '0000-00-00 00:00:00',
`created_at` timestamp NULL DEFAULT '0000-00-00 00:00:00',
 KEY `FK_shop_stat_viewgoods_shop_stat_session_id` (`id_session`),
 CONSTRAINT `FK_shop_stat_viewgoods_shop_stat_session_id` FOREIGN KEY (`id_session`) REFERENCES `shop_stat_session` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;");
