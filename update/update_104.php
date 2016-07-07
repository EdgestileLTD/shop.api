<?php
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