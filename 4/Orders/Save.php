<?php

    require_once "Payments/functions.php";

    function saveProducts($idsOrders, $products) {

        $idsUpdate = '';
        foreach ($products as $p)
            if ($p->id) {
                if (!empty($idsUpdate))
                    $idsUpdate .= ',';
                $idsUpdate .= $p->id;
            }

        $idsStr = implode(",", $idsOrders);
        se_db_query("UPDATE shop_price sp
            INNER JOIN shop_tovarorder st ON sp.id = st.id_price
            SET sp.presence_count = sp.presence_count + st.count
            WHERE st.id_order IN ({$idsStr}) AND sp.presence_count IS NOT NULL AND sp.presence_count >= 0");
        se_db_query("UPDATE shop_modifications sm
            INNER JOIN shop_tovarorder st ON sm.id IN (st.modifications)
            INNER JOIN shop_price sp ON sp.id = st.id_price
            SET sm.count = sm.count + st.count
            WHERE st.id_order IN ({$idsStr}) AND sm.count IS NOT NULL AND sm.count >= 0");

        $u = new seTable('shop_tovarorder','st');
        if (!empty($idsUpdate))
            $u->where('NOT `id` IN (' . $idsUpdate . ') AND id_order in (?)', $idsStr)->deletelist();
        else $u->where('id_order in (?)', $idsStr)->deletelist();

        // новый товары/услуги заказа
        foreach ($products as $p) {
            if (!$p->id) {
                foreach ($idsOrders as $idOrder)
                    $data[] = array('id_order' => $idOrder, 'id_price' => $p->idPrice, 'article' => $p->article,
                        'nameitem' => $p->name, 'price' => $p->price,
                        'discount' => $p->discount, 'count' => $p->count, 'modifications' => $p->idsModifications,
                        'license' => $p->license, 'commentary' => $p->note, 'action' => $p->action);
            } else {
                $u = new seTable('shop_tovarorder','sto');
                $u->select("modifications");
                $u->where("id=?", $p->id);
                $u->fetchOne();
                if ($u->modifications)
                    $p->idsModifications = $u->modifications;
            }
            if ($p->idPrice && $p->count > 0) {
                se_db_query("UPDATE shop_price SET presence_count = presence_count - '{$p->count}'
                    WHERE id = {$p->idPrice} AND presence_count IS NOT NULL AND presence_count >= 0");
            }
            if ($p->idsModifications && $p->idPrice) {
                if ($p->count > 0)
                    se_db_query("UPDATE shop_modifications
                        SET count = count  - '{$p->count}'
                        WHERE id IN ({$p->idsModifications}) AND count IS NOT NULL AND count >= 0 AND id_price = {$p->idPrice}");
            }
        }
        if (!empty($data))
            se_db_InsertList('shop_tovarorder', $data);

        // обновление товаров/услугов заказа
        foreach ($products as $p)
            if ($p->id) {
                $u = new seTable('shop_tovarorder','st');
                $isUpdated = false;
                $isUpdated |= setField(false, $u, $p->article, 'article');
                $isUpdated |= setField(false, $u, $p->name, 'nameitem');
                $isUpdated |= setField(false, $u, $p->price, 'price');
                $isUpdated |= setField(false, $u, $p->discount, 'discount');
                $isUpdated |= setField(false, $u, $p->count, 'count');
                $isUpdated |= setField(false, $u, $p->license, 'license');
                $isUpdated |= setField(false, $u, $p->note, 'commentary');
                $isUpdated |= setField(false, $u, $p->action, 'action');
                $u->where('id=?', $p->id);
                if ($isUpdated)
                    $u->save();
            }
    }

    function savePayments($payments) {
        global $json;
        $token = $json->token;
        $idsPayments = array();
        $idsOrders = array();
        foreach ($payments as $payment) {
            $idsOrders[] = $payment->idOrder;
            if ($payment->id)
                $idsPayments[] = $payment->id;
            else {
                $url = API_ROOT_URL . "/Orders/Payments/Save.api";
                $ch = curl_init($url);
                $payment->token = $token;
                $apiData = json_encode($payment);
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
                curl_setopt($ch, CURLOPT_POSTFIELDS, $apiData);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                        'Content-Type: application/json',
                        'Content-Length: ' . strlen($apiData))
                );
                $result = json_decode(curl_exec($ch), 1);
                if ($result["status"] == "ok") {
                    $idsPayments[] = $result["data"]["id"];
                }
            }
        }
        $p = new seTable('shop_order_payee','sop');
        $p->select("id");
        $p->where('id_order IN (?)', implode(",", $idsOrders));
        $p->andwhere('NOT id IN (?)', implode(",", $idsPayments));
        $p->deleteList();
    }

    function saveDelivery($idsOrders, $json) {

        $p = new seTable('shop_delivery','sd');
        $p->select("id");
        $p->where('id_order=?', $idsOrders[0]);
        $p->fetchOne();
        if ($p->id) {
            $u = new seTable('shop_delivery','sd');
            $isUpdated = false;
            $isUpdated |= setField(false, $u, $idsOrders[0], 'id_order');
            $isUpdated |= setField(false, $u, $json->nameRecipien, 'name_recipient');
            $isUpdated |= setField(false, $u, $json->deliveryPhone, 'telnumber');
            $isUpdated |= setField(false, $u, $json->deliveryEmail, 'email');
            $isUpdated |= setField(false, $u, $json->deliveryCallTime, 'calltime');
            $isUpdated |= setField(false, $u, $json->deliveryAddress, 'address');
            $isUpdated |= setField(false, $u, $json->deliveryPostIndex, 'postindex');
            $u->where('id=?', $p->id);
            if ($isUpdated)
                $u->save();
        } else {
            foreach($idsOrders as $idOrder)
                $data[] = array('id_order' => $idOrder, 'name_recipient' => $json->nameRecipient, 'telnumber' => $json->deliveryPhone,
                    'email' =>  $json->deliveryEmail, 'calltime' => $json->deliveryCallTime, 'address' => $json->deliveryAddress,
                    'postindex' => $json->deliveryPostIndex);
            if (!empty($data))
                se_db_InsertList('shop_delivery', $data);
        }
    }

    function saveDynFields($idsOrders, $dynFields) {
        foreach ($dynFields as $field) {
            if ($field->id) {
                $u = new seTable('shop_order_userfields', 'sou');
                $isUpdated = false;
                $isUpdated |= setField(false, $u, $field->value, 'value');
                $u->where('id=?', $field->id);
                if ($isUpdated) {
                    $u->save();
                }
            }
        }
        $data = array();
        foreach($idsOrders as $idOrder) {
            foreach ($dynFields as $field)
                if (empty($field->id) && ($field->value != ""))
                    $data[] = array('id_order' => $idOrder, 'id_userfield' => $field->idMain, 'value' => $field->value);
        }
        if (!empty($data)) {
            se_db_InsertList('shop_order_userfields', $data);
        }
    }

    $ids = array();
    if (empty($json->ids) && !empty($json->id))
        $ids[] = $json->id;
    else $ids = $json->ids;
    $isNew = empty($ids);

    if (!$isNew)
        $idsStr = implode(",", $ids);

    if ($isNew || !empty($ids)) {
        $u = new seTable('shop_order');
        if ($isNew) {
            $u->id = $ids[0];
            if (empty($json->dateOrder))
                $json->dateOrder = date("Y-m-d");
        }

        $isUpdated = false;
        $isUpdated |= setField($isNew, $u, $json->idCustomer, 'id_author');
        $isUpdated |= setField($isNew, $u, $json->idManager, 'id_admin');
        $isUpdated |= setField($isNew, $u, $json->dateOrder, 'date_order');
        if (empty($json->datePayee) && ($json->statusOrder == 'Y' || $json->statusOrder == 'K'))
            $json->datePayee = date("Y-m-d");
        $isUpdated |= setField($isNew, $u, $json->datePayee, 'date_payee');
        $isUpdated |= setField($isNew, $u, $json->deliveryDate, 'delivery_date');
        $isUpdated |= setField($isNew, $u, $json->dateCredit, 'date_credit');
        $isUpdated |= setField($isNew, $u, $json->inPayee, 'inpayee', "enum('N', 'Y') default 'N'", 1);
        if (isset($json->isCanceled)) {
            if ($json->isCanceled)
                $isUpdated |= setField($isNew, $u, 'Y', 'is_delete', "enum('N', 'Y') default 'N'", 1);
            else $isUpdated |= setField($isNew, $u, 'N', 'is_delete', "enum('N', 'Y') default 'N'", 1);
        }
        $isUpdated |= setField($isNew, $u, $json->managers, 'managers', 'text default NULL');
        $isUpdated |= setField($isNew, $u, $json->paymentType, 'payment_type', 'int(10) default 1', 1);
        if (isset($json->statusOrder) && empty($json->statusOrder))
            $json->statusOrder = 1;
        $isUpdated |= setField($isNew, $u, $json->statusOrder, 'id_status');
        $isUpdated |= setField($isNew, $u, $json->statusDelivery, 'delivery_status');
        $isUpdated |= setField($isNew, $u, $json->currency, 'curr');
        $isUpdated |= setField($isNew, $u, $json->discountSum, 'discount', 'decimal(10,2) default 0.00');
        $isUpdated |= setField($isNew, $u, $json->deliverySum, 'delivery_payee', 'decimal(10,2) default 0.00');
        $isUpdated |= setField($isNew, $u, $json->note, 'commentary');
        $isUpdated |= setField($isNew, $u, $json->payeeDoc, 'payee_doc');
        $isUpdated |= setField($isNew, $u, $json->account, 'account');
        $isUpdated |= setField($isNew, $u, $json->transactSum, 'transact_amount');
        $isUpdated |= setField($isNew, $u, $json->transactId, 'transact_id');
        $isUpdated |= setField($isNew, $u, $json->transactCurrency, 'transact_curr');
        $isUpdated |= setField($isNew, $u, $json->deliveryId, 'delivery_type', 'varchar(20) default NULL', 1);
        $isUpdated |= setField($isNew, $u, $json->idUserAccountOut, 'id_user_account_out', 'int(10) default NULL', 1);

        if ($isUpdated){
            if (!empty($idsStr))
                $u->where('id in (?)', $idsStr);
            $idv = $u->save();
            if ($isNew)
                $ids[] = $idv;
        }

        if ($ids && isset($json->items))
            saveProducts($ids, $json->items);
        if ($ids && (isset($json->nameRecipient) || isset($json->deliveryPhone) || isset($json->deliveryEmail) ||
                isset($json->deliveryCallTime) || isset($json->deliveryAddress) || isset($json->deliveryPostIndex)))
            saveDelivery($ids, $json);
        if ($ids && isset($json->payments))
            savePayments($json->payments);
        if ($ids && isset($json->dynFields))
            saveDynFields($ids, $json->dynFields);
        if (count($ids) && isset($json->payments))
            checkStatusOrder($ids[0]);

        if ($json->isSendToEmail) {
            if ($isNew)
                $codeMail = 'orderuser';
            else {
                $codeMail = 'orduserch';
                // отправлен
                if (isset($json->statusDelivery) && $json->statusDelivery == "P")
                    $codeMail = 'orderdelivP';
                // в работе
                if (isset($json->statusDelivery) && $json->statusDelivery == "M")
                    $codeMail = 'orderdelivM';
                // доставлен
                if (isset($json->statusDelivery) && $json->statusDelivery == "Y")
                    $codeMail = 'orderdelivY';
                if (isset($json->statusOrder) && $json->statusOrder == "Y")
                    $codeMail = 'payuser';
            }
            if ($codeMail) {
                $urlSendEmail = 'http://' . $json->hostname . '/upload/sendmailorder.php';
                $params = array(
                    'lang' => 'rus',
                    'idorder' => $ids[0],
                    'codemail' => $codeMail
                );
                $result = file_get_contents($urlSendEmail, false, stream_context_create(array(
                    'http' => array(
                        'method' => 'POST',
                        'header' => 'Content-type: application/x-www-form-urlencoded',
                        'content' => http_build_query($params)
                    )
                )));
            }
        }
    }

    $data['id'] = $ids[0];
    $status = array();
    if (!se_db_error()) {
        $status['status'] = 'ok';
        $status['data'] = $data;
    } else {
        $status['status'] = 'error';
        $status['errortext'] = se_db_error();
    }

    outputData($status);
