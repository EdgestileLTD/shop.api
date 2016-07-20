<?php
    se_db_query("UPDATE shop_price SET presence_count = -1 WHERE presence_count IS NULL");