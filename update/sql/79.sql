CREATE TABLE `news_subscriber_se_group` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_news` int(10) unsigned DEFAULT NULL,
  `id_group` int(10) unsigned DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00' ON UPDATE CURRENT_TIMESTAMP,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8