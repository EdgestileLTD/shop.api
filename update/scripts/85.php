<?php

se_db_query("ALTER TABLE `shop_discount_links` ADD `id_usergroup` INT UNSIGNED DEFAULT NULL AFTER `id_user`, ADD INDEX (`id_usergroup`);");