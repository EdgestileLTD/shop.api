<?php
se_db_query("ALTER TABLE shop_deliverytype
  ADD COLUMN id_parent INT(10) UNSIGNED DEFAULT NULL AFTER id");
se_db_query("ALTER TABLE shop_deliverytype
    ADD CONSTRAINT FK_shop_deliverytype_shop_deliverytype_id FOREIGN KEY (id_parent)
        REFERENCES shop_deliverytype(id) ON DELETE CASCADE ON UPDATE RESTRICT");
		
se_db_query("CREATE TABLE IF NOT EXISTS `shop_order_payee` (
`id` int(10) unsigned NOT NULL AUTO_INCREMENT,
`id_order` int(10) unsigned NOT NULL,
`id_author` int(10) unsigned NOT NULL COMMENT 'Идентификатор плательщика',
`num` mediumint(9) unsigned NOT NULL COMMENT 'Номер платежа',
`date` datetime NOT NULL COMMENT 'Дата платежа',
`year` smallint(6) unsigned NOT NULL DEFAULT '2000' COMMENT 'Год платежа',
`payment_type` int(10) unsigned NOT NULL DEFAULT '1' COMMENT 'С лицевого счета: 0 или ид. платежа > 0 (таблица: shop_payment)',
`id_payment` int(10) unsigned DEFAULT NULL,
`id_manager` int(10) unsigned DEFAULT NULL COMMENT 'Идентификатор пользователя',
`amount` decimal(10,2) unsigned NOT NULL COMMENT 'Сумма платежа',
`note` varchar(255) DEFAULT NULL COMMENT 'Примечание к платежу',
`id_user_account_in` int(10) unsigned DEFAULT NULL,
`id_user_account_out` int(10) unsigned DEFAULT NULL,
`updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
`created_at` timestamp NULL DEFAULT '0000-00-00 00:00:00',
PRIMARY KEY (`id`),
CONSTRAINT FK_shop_order_payee_se_user_id FOREIGN KEY (id_author)
REFERENCES se_user (id) ON DELETE CASCADE ON UPDATE RESTRICT,
CONSTRAINT FK_shop_order_payee_shop_order_id FOREIGN KEY (id_order)
REFERENCES shop_order (id) ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 COMMENT='Платежи к заказам';");

if (!se_db_is_index('shop_img', 'UK_shop_img')){
  	se_db_query("ALTER TABLE shop_img
     	ADD UNIQUE INDEX UK_shop_img (id_price, picture);");

    se_db_query("INSERT LOW_PRIORITY IGNORE
       	INTO `shop_img` (`id_price`,`picture`,`picture_alt`, `sort`, `default`)
       	SELECT `sp`.`id`, `sp`.`img`, `sp`.`img_alt`, 0, 1 FROM `shop_price` AS `sp` WHERE `sp`.`img` IS NOT NULL AND TRIM(`sp`.`img`) <> ''
       	ON DUPLICATE KEY UPDATE `default` = 1;");
}

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

se_db_query("ALTER TABLE `shop_order` CHANGE `delivery_payee` `delivery_payee` DOUBLE(10,2) UNSIGNED NOT NULL DEFAULT '0.00';");