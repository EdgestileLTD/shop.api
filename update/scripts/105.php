<?php

se_db_query("UPDATE shop_order set id_admin = NULL WHERE id_admin < 1");