<?php

se_db_query("CREATE TABLE IF NOT EXISTS `permission_role` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AVG_ROW_LENGTH=16384 DEFAULT CHARSET=utf8 COMMENT='Роли пользователей';");

se_db_query("CREATE TABLE IF NOT EXISTS `permission_role_user` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_role` int(10) UNSIGNED NOT NULL,
  `id_user` int(10) UNSIGNED DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `UK_permission_role_user` (`id_role`,`id_user`),
  KEY `FK_permission_role_user_se_user_id` (`id_user`),
  CONSTRAINT `FK_permission_role_user_permission_role_id` FOREIGN KEY (`id_role`) REFERENCES `permission_role` (`id`) ON DELETE CASCADE,
  CONSTRAINT `FK_permission_role_user_se_user_id` FOREIGN KEY (`id_user`) REFERENCES `se_user` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;");

se_db_query("DROP TABLE IF EXISTS `permission_object`;");
se_db_query("CREATE TABLE IF NOT EXISTS `permission_object` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` varchar(100) NOT NULL,
  `name` varchar(255) NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=19 AVG_ROW_LENGTH=1260 DEFAULT CHARSET=utf8;");

se_db_query("INSERT INTO `permission_object` (`id`, `code`, `name`, `updated_at`, `created_at`) VALUES
(1, 'contacts', 'Контакты', '0000-00-00 00:00:00', '2016-07-07 08:00:34'),
(2, 'orders', 'Заказы', '0000-00-00 00:00:00', '2016-07-07 08:00:34'),
(3, 'products', 'Товары', '0000-00-00 00:00:00', '2016-07-07 08:00:34'),
(4, 'comments', 'Комментарии', '0000-00-00 00:00:00', '2016-07-07 08:00:34'),
(5, 'reviews', 'Отзывы', '0000-00-00 00:00:00', '2016-07-07 08:00:34'),
(6, 'news', 'Новости', '0000-00-00 00:00:00', '2016-07-07 08:00:34'),
(7, 'images', 'Картинки', '0000-00-00 00:00:00', '2016-07-07 08:00:34'),
(8, 'deliveries', 'Доставки', '0000-00-00 00:00:00', '2016-07-07 08:00:34'),
(9, 'paysystems', 'Платежные системы', '0000-00-00 00:00:00', '2016-07-07 08:00:34'),
(10, 'mails', 'Шаблоны писем', '0000-00-00 00:00:00', '2016-07-07 08:00:34'),
(11, 'settings', 'Настройки магазина', '0000-00-00 00:00:00', '2016-07-07 08:00:34'),
(15, 'currencies', 'Настройки валют', '0000-00-00 00:00:00', '2016-07-07 08:00:34'),
(18, 'payments', 'Платежи', '0000-00-00 00:00:00', '2016-07-07 08:00:34');");

se_db_query("ALTER TABLE `se_user` ADD `is_manager` BOOLEAN NOT NULL DEFAULT FALSE AFTER `is_super_admin`, ADD INDEX (`is_manager`);");