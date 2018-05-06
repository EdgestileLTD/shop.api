<?php
function checkStatusOrder($idOrder)
{
    $u = new seTable('shop_order', 'so');
    $u->select('(SUM((st.price - IFNULL(st.discount, 0)) * st.count) - IFNULL(so.discount, 0) +
                IFNULL(so.delivery_payee, 0)) sum_order');
    $u->innerjoin('shop_tovarorder st', 'st.id_order = so.id');
    $u->where('so.id = ?', $idOrder);
    $u->groupby('so.id');
    $result = $u->getList();
    $sumOrder = 0;
    foreach ($result as $item)
        $sumOrder = $item['sum_order'];
    unset($u);

    $u = new seTable('shop_order_payee', 'sop');
    $u->select('SUM(sop.amount) sum_payee, MAX(sop.date) date_payee');
    $u->where(' sop.id_order = ?', $idOrder);
    $result = $u->getList();
    $sumPayee = 0;
    foreach ($result as $item) {
        $sumPayee = $item['sum_payee'];
        $datePayee = $item['date_payee'];
    }
    unset($u);

    if ($sumPayee >= $sumOrder) {
        $u = new seTable('shop_order', 'so');

        setField(0, $u, 'Y', 'status');
        setField(0, $u, 'N', 'is_delete');
        setField(0, $u, $datePayee, 'date_payee');

        $u->where('id = ?', $idOrder);
        $u->save();
    };

    unset($u);
}

function correctTablePayee()
{
    $sql = "CREATE TABLE IF NOT EXISTS `shop_order_payee` (
	`id` int(10) unsigned NOT NULL AUTO_INCREMENT,
	`id_order` int(10) unsigned NOT NULL,
	`id_author` int(10) unsigned NOT NULL COMMENT 'Идентификатор плательщика',
	`num` mediumint(9) unsigned NOT NULL COMMENT 'Номер платежа',
	`date` datetime NOT NULL COMMENT 'Дата платежа',
	`year` smallint(6) unsigned NOT NULL DEFAULT '2000' COMMENT 'Год платежа',
	`payment_type` int(10) unsigned NOT NULL DEFAULT '1' COMMENT 'С лицевого счета: 0 или ид. платежа > 0 (таблица: shop_payment)',
	`id_payment` int(10) unsigned DEFAULT NULL,
	`id_manager` int(10) unsigned DEFAULT NULL COMMENT 'Идентификатор пользователя',
	`amount` decimal(10,2) unsigned NOT NULL COMMENT 'Сумма платежа',
	`note` varchar(255) DEFAULT NULL COMMENT 'Примечание к платежу',
	`id_user_account_in` int(10) unsigned DEFAULT NULL,
	`id_user_account_out` int(10) unsigned DEFAULT NULL,
	`updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	`created_at` timestamp NULL DEFAULT '0000-00-00 00:00:00',
	 PRIMARY KEY (`id`),
	 CONSTRAINT FK_shop_order_payee_se_user_id FOREIGN KEY (id_author)
	 REFERENCES se_user (id) ON DELETE CASCADE ON UPDATE RESTRICT,
	 CONSTRAINT FK_shop_order_payee_shop_order_id FOREIGN KEY (id_order)
	 REFERENCES shop_order (id) ON DELETE CASCADE ON UPDATE RESTRICT
	) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 COMMENT='Платежи к заказам';";
    se_db_query($sql);
}