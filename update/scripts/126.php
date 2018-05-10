<?php

se_db_query('ALTER TABLE shop_feature_value_list
  DROP INDEX UK_shop_feature_value_list_cod,
  ADD UNIQUE INDEX UK_shop_feature_value_list_cod (code, id_feature);');
