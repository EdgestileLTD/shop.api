<?php
se_db_query("ALTER TABLE shop_group_feature DROP FOREIGN KEY shop_group_feature_ibfk_1;");
se_db_query("ALTER TABLE `shop_group_feature` ADD FOREIGN KEY (`id_group`) REFERENCES `shop_modifications_group`(`id`) ON DELETE CASCADE ON UPDATE CASCADE;");
