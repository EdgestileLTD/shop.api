<<<<<<< Updated upstream
ALTER TABLE shop_price
  ADD COLUMN min_count DOUBLE(10, 3) NOT NULL AFTER step_count;
=======
ALTER TABLE `shop_userfields` CHANGE `data` `data` ENUM('contact','order','company','productgroup','product') CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT 'contact';
CREATE TABLE `shop_group_userfields` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_shopgroup` int(10) UNSIGNED NOT NULL,
  `id_userfield` int(10) UNSIGNED NOT NULL,
  `value` text,
  `updated_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00' ON UPDATE CURRENT_TIMESTAMP,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `id_shopgroup` (`id_shopgroup`),
  KEY `id_userfield` (`id_userfield`),
  CONSTRAINT `shop_group_userfields_ibfk_1` FOREIGN KEY (`id_shopgroup`) REFERENCES `shop_group` (`id`) ON DELETE CASCADE ON UPDATE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `shop_price_userfields` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_price` int(10) UNSIGNED NOT NULL,
  `id_userfield` int(10) UNSIGNED NOT NULL,
  `value` text,
  `updated_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00' ON UPDATE CURRENT_TIMESTAMP,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `id_price` (`id_price`),
  KEY `id_userfield` (`id_userfield`),
  CONSTRAINT `shop_group_userfields_ibfk_1` FOREIGN KEY (`id_price`) REFERENCES `shop_price` (`id`) ON DELETE CASCADE ON UPDATE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
>>>>>>> Stashed changes
