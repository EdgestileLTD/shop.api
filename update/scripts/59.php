<?php

se_db_query("ALTER TABLE shop_contacts ADD `additional_phones` varchar(255) DEFAULT NULL AFTER `phone`");