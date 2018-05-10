
INSERT INTO shop_setting_groups(id, name, description, sort) VALUES
(25, 'Сервер SMTP ', 'Настройки для отправки почты через SMTP сервер', 3);

INSERT INTO shop_settings(id, code, `type`, name, `default`, list_values, id_group, description, sort, enabled) VALUES
(35, 'smtp_server', 'string', 'Адрес сервера', 'smtp.yandex.ru', NULL, 25, 'Адрес сервера исходящей почты (SMTP)', 0, 1),
(36, 'smtp_port', 'string', 'Порт', '445', NULL, 25, 'Порт SMTP', 1, 1),
(37, 'smtp_login', 'string', 'Имя пользователя', '', NULL, 25, 'Ваш адрес электронной почты', 2, 1),
(38, 'smtp_password', 'string', 'Пароль', '', NULL, 25, 'Пароль от Вашего адреса электронной почты', 3, 1);


INSERT INTO shop_setting_values(id_setting, `value`) VALUES
(35, 'smtp.yandex.ru'),
(36, '445'),
(37, ''),
(38, '');