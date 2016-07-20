<?php

se_db_query('INSERT IGNORE INTO shop_img (id_price, picture, picture_alt, title, sort, `default`)
  SELECT sp.id, sp.img, sp.img_alt, sp.name, 0, 1 FROM shop_price sp WHERE sp.img IS NOT NULL AND sp.img <> ""');