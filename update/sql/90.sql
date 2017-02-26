CREATE TABLE IF NOT EXISTS `shop_geo_variables` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_contact` int(10) UNSIGNED NOT NULL,
  `id_variable` int(10) UNSIGNED NOT NULL,
  `value` varchar(255) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `id_variables` (`id_variable`),
  KEY `id_contacts` (`id_contact`),
  CONSTRAINT `shop_geo_variables_ibfk_1` FOREIGN KEY (`id_contact`) REFERENCES `shop_contacts` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `shop_geo_variables_ibfk_2` FOREIGN KEY (`id_variable`) REFERENCES `shop_variables` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
