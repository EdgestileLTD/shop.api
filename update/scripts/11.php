<?php
se_db_add_field('person','email_valid', "enum('Y','N','C') DEFAULT 'C'");
se_db_add_index('person','email_valid', 1);
se_db_add_index('person','created_at', 1);
