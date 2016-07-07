<?php

se_db_query("ALTER TABLE permission_object_role
  ADD UNIQUE INDEX UK_permission_object_role (id_object, id_role);");

se_db_query("INSERT INTO permission_object(id, code, name) VALUES 
    (1, 'contacts', 'Контакты'), (2, 'orders', 'Заказы'), (3, 'products', 'Товары'),
    (4, 'comments', 'Комментарии'), (5, 'reviews', 'Отзывы'), (6, 'news', 'Новости'), 
    (7, 'images', 'Картинки'), (8, 'deliveries', 'Доставки'),
    (9, 'paysystems', 'Платежные системы'), (10, 'mails', 'Шаблоны писем'),
    (11, 'settings', 'Настройки магазина'), (15, 'currencies', 'Настройки валют');");

se_db_query("INSERT INTO permission_role(id, name) VALUE (1, 'Менеджер');");

se_db_query("
    INSERT INTO permission_object_role(id_object, id_role, mask) VALUES
    (1, 1, 8), (2, 1, 8), (15, 1, 8), (11, 1, 8), (6, 1, 8), (10, 1, 8), (4, 1, 8),    
    (3, 1, 8), (7, 1, 8), (9, 1, 8), (5, 1, 8), (8, 1, 8);");
