<?php

se_db_query("UPDATE money SET name = 'RUB' WHERE name = 'RUR'");
se_db_query("UPDATE money_title SET name = 'RUB' WHERE name = 'RUR'");