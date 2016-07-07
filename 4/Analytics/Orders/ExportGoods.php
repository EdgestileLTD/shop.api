<?php
    header( 'Content-Type: application/octet-stream' );
    header( 'Content-Disposition: attachment; filename="ExportOrdersGoods.csv"' );
    header( 'Content-Transfer-Encoding: binary' );

    $query = "SELECT
      shop_tovarorder.id 		AS 'Номер записи',
      shop_tovarorder.article	AS 'Артикул товара',
      shop_tovarorder.nameitem	AS 'Наименование товара',
      shop_tovarorder.price		AS 'Цена товара',
      shop_tovarorder.discount	AS 'Скидка на товар',
      shop_tovarorder.count		AS 'Количество товара',
      shop_tovarorder.license	AS 'Номер лицензии товара',
      shop_tovarorder.commentary	AS 'Комментарий к товару',

      shop_order.id			AS 'Номер заказа',
      shop_order.date_order		AS 'Дата заказа',
      shop_order.discount		AS 'Скидка на заказ',
      shop_order.curr		AS 'Валюта заказа',
      shop_order.date_payee		AS 'Дата оплаты заказа',
      shop_order.payee_doc		AS 'Платежный документ заказа',
      shop_order.commentary		AS 'Комментарий к заказу',
      shop_order.delivery_payee	AS 'Стоимость доставки заказа',
      shop_order.delivery_status	AS 'Статус доставки заказа',
      shop_order.delivery_date	AS 'Дата доставки заказа',

      se_user.username		AS 'Логин клиента',

      person.last_name		AS 'Фамилия клиента',
      person.first_name		AS 'Имя клиента',
      person.sec_name		AS 'Отчество клиента',
      person.sex			AS 'Пол клиента',
      person.birth_date		AS 'Дата рождения клиента',
      person.nick			AS 'Псевдоним клиента',
      person.doc_ser		AS 'Серия документа клиента',
      person.doc_num		AS 'Номер документа клиента',
      person.doc_registr		AS 'Документ клиента выдан',
      person.email			AS 'Эл.почта клиента',
      person.post_index		AS 'Почтовый индекс клиента',
      person.addr			AS 'Адрес клиента',
      person.phone			AS 'Телефон клиента',
      person.icq			AS 'ICQ клиента',
      person.skype			AS 'Skype клиента',
      person.discount		AS 'Скидка клиента',
      person.reg_date		AS 'Дата регистрации клиента',
      person.reg_info		AS 'Регистрационные данные клиента',
      person.note			AS 'Заметка о клиенте',
      person.subscriber_news	AS 'Клиент подписан на новости(статус)',
      person.enable			AS 'Клиент активен/заблокирован(статус)',
      person.email_valid	AS 'Адрес клиента валиден(статус)',
      person.referer		AS 'Источник клиента'

    FROM shop_tovarorder
    LEFT JOIN shop_order	ON shop_tovarorder.id_order = shop_order.id
    LEFT JOIN se_user 	    ON shop_order.id_author = se_user.id
    LEFT JOIN person 	    ON se_user.id = person.id";

    $stmt = se_db_query($query);
    $list = array();
    $header = array();
    while ($row = $stmt->fetch_assoc()) {
        if (!$header) {
            $header = array_keys($row);
            $list[] = $header;
        }
        $list[] = $row;
    }

    $root = API_ROOT;
    $dir = 'AppData/' . $json->hostname . '/exports/csv/analytics';

    if (!file_exists($root . $dir)) {
        $dirs = explode('/', $dir);
        $path = $root;
        foreach($dirs as $d){
            $path .= $d;
            if(!file_exists($path))
                mkdir($path, 0700);
            $path .= '/';
        }
    }
    $dir = $root . $dir;
    if (file_exists($dir))
        foreach (glob($dir . '/*') as $file)
            unlink($file);

    $fileName = "OrdersGoods.csv";
    $fileName = $dir . '/' . $fileName;
    $fp = fopen($fileName, 'w');
    foreach ($list as $line) {
        foreach ($line as &$str)
            $str = iconv('utf-8', 'CP1251', $str);
        fputcsv($fp, $line, ";");
    }
    fclose($fp);

    echo file_get_contents($fileName);



