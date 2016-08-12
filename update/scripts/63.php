<?php

se_db_query("INSERT INTO sms_providers(id, name, settings, is_active) VALUES
(1, 'sms.ru', '{\"api_id\":{\"type\":\"string\",\"value\":\"\"}}', 1);");

se_db_query("INSERT INTO sms_providers(id, name, settings, is_active) VALUES
(2, 'qtelecom.ru', '{\"login\":{\"type\":\"string\",\"value\":\"\"},\"password\":{\"type\":\"string\",\"value\":\"\"}}', 0);");

se_db_query("INSERT INTO sms_templates(id, code, name, text, is_active) VALUES
(1, 'orderadm', 'SMS администратору о заказе', 'Оформлен заказ №[SHOP_ORDER_NUM]. Сумма:[SHOP_ORDER_SUMM] Сумма доставки:[SHOP_ORDER_DEVILERY]', 1);");