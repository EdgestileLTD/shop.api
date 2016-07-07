<?php

se_db_query("INSERT INTO shop_setting_groups(id, name, description, sort) VALUES
    (1, 'Параметры изображения', 'Параметры изображения при выгрузке', 1);");

se_db_query("INSERT INTO shop_settings(id, code, type, name, `default`, list_values, id_group, description, sort, enabled) VALUES
    (1, 'size_picture', 'string', 'Максимальный размер изображения', '650x650', NULL, 1,
    'При включенном параметре все изображения при загрузке в программе будут сжиматься пропорционально до заданных значений. Значения задаются в пикселях: ШИРИНАxВЫСОТА (пример: 1920x1080).', 0, 0);");