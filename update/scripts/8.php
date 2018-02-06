<?php

if (!se_db_is_field('shop_modifications','id_exchange')){ 
    se_db_add_field('shop_modifications', 'id_exchange', "varchar(40) default NULL AFTER `default`");
}

if (!se_db_is_field('shop_modifications','code')){ 
    se_db_add_field('shop_modifications', 'code', "varchar(40) default NULL AFTER `id_price`");
}


if (!se_db_is_field('shop_modifications','description')){ 
    se_db_add_field('shop_modifications', 'description', "TEXT  AFTER `id_exchange`");
}
/*
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
*/
se_db_query("ALTER TABLE  `shop_tovarorder` CHANGE  `count`  `count` DOUBLE( 10, 3 ) UNSIGNED NOT NULL");
se_db_query("ALTER TABLE  `shop_modifications` CHANGE  `count`  `count` DOUBLE( 10, 3 ) NULL DEFAULT NULL");

    se_db_add_field('shop_modifications', 'value_opt', "double(10,2) NOT NULL AFTER `value`");
    se_db_add_field('shop_modifications', 'value_opt_corp', "double(10,2) NOT NULL AFTER `value_opt`");

if (!se_db_is_field('shop_modifications','`default`')){ 
    se_db_query("ALTER TABLE  `shop_modifications` ADD `default` BOOLEAN NOT NULL DEFAULT FALSE AFTER `sort`, ADD INDEX (  `default` )");
}

if (!se_db_is_field('shop_group_filter','`expanded`')){ 
    se_db_query("ALTER TABLE  `shop_group_filter` ADD  `expanded` BOOLEAN NOT NULL DEFAULT FALSE AFTER  `default_filter`");
}

if (!se_db_is_field('shop_img','picture_alt')){ 
    se_db_query("ALTER TABLE  `shop_img` ADD  `picture_alt` varchar(255) DEFAULT NULL AFTER  `picture`");
    //se_db_add_field('shop_img', 'picture_alt', "picture_alt varchar(255) DEFAULT NULL AFTER  `picture`");
}

if (!se_db_is_field('shop_img','sort')){ 
    se_db_query("ALTER TABLE  `shop_img` ADD  `sort` int(11) DEFAULT 0 AFTER  `picture`");
    //se_db_add_field('shop_img', 'sort', "sort int(11) NOT NULL DEFAULT 0 AFTER  `title`");
}

if (!se_db_is_field('shop_img','`default`')){
    se_db_query("ALTER TABLE  `shop_img` ADD  `default` BOOLEAN NOT NULL DEFAULT FALSE AFTER  `title`");
        //se_db_add_field('shop_img', '`default`', "`default` tinyint(1) NOT NULL DEFAULT 0 AFTER  `title`");
}

if (!se_db_is_field('shop_modifications_img','sort')){ 
    se_db_query("ALTER TABLE  `shop_modifications_img` ADD  `sort` int(11) NOT NULL DEFAULT 0 AFTER  `id_img`");
    //se_db_add_field('shop_modifications_img', 'sort', "sort int(11) NOT NULL DEFAULT 0 AFTER  `id_img`");
}

se_db_query("CREATE TABLE IF NOT EXISTS `shop_reviews` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_price` int(10) unsigned NOT NULL,
  `id_user` int(10) unsigned NOT NULL,
  `mark` smallint(1) unsigned NOT NULL,
  `merits` text,
  `demerits` text,
  `comment` text NOT NULL,
  `use_time` smallint(1) unsigned NOT NULL DEFAULT '1',
  `date` datetime NOT NULL,
  `likes` int(10) unsigned NOT NULL DEFAULT '0',
  `dislikes` int(10) unsigned NOT NULL DEFAULT '0',
  `active` tinyint(1) unsigned NOT NULL DEFAULT '1',
  `updated_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00' ON UPDATE CURRENT_TIMESTAMP,
  `created_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY (`id`),
  UNIQUE KEY `UK_shop_reviews` (`id_price`,`id_user`),
  KEY `FK_shop_reviews_se_user_id` (`id_user`),
  CONSTRAINT `FK_shop_reviews_se_user_id` FOREIGN KEY (`id_user`) 
  REFERENCES `se_user` (`id`),
  CONSTRAINT `FK_shop_reviews_shop_price_id` FOREIGN KEY (`id_price`) 
  REFERENCES `shop_price` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;
");

se_db_query("CREATE TABLE IF NOT EXISTS `shop_reviews_votes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_review` int(10) unsigned NOT NULL,
  `id_user` int(10) unsigned NOT NULL,
  `vote` smallint(1) NOT NULL DEFAULT '1',
  `updated_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00' ON UPDATE CURRENT_TIMESTAMP,
  `created_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY (`id`),
  UNIQUE KEY `UK_shop_reviews_votes` (`id_review`,`id_user`),
  KEY `FK_shop_reviews_votes_se_user_id` (`id_user`),
  CONSTRAINT `FK_shop_reviews_votes_se_user_id` FOREIGN KEY (`id_user`) REFERENCES `se_user` (`id`),
  CONSTRAINT `FK_shop_reviews_votes_shop_reviews_id` FOREIGN KEY (`id_review`) REFERENCES `shop_reviews` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;");


se_db_query("ALTER TABLE  `money` CHANGE  `kurs`  `kurs` DOUBLE( 20, 6 ) NOT NULL");

if (!se_db_is_field('money_title','minsum')){
   se_db_query("ALTER TABLE  `money_title` ADD  `minsum` DOUBLE( 10, 2 ) NOT NULL DEFAULT  '0.01' AFTER  `cbr_kod`");
}
