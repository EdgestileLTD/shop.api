CREATE TABLE `accounts` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `main_login` varchar(50) DEFAULT NULL,
  `alias` varchar(255) DEFAULT NULL,
  `project` varchar(50) NOT NULL,
  `login` varchar(50) NOT NULL,
  `hash` varchar(50) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00' ON UPDATE CURRENT_TIMESTAMP,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `accounts_id_uindex` (`id`),
  UNIQUE KEY `accounts_main_login_project_login_pk` (`main_login`,`project`,`login`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8

