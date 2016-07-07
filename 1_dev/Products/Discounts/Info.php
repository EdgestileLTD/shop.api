<?php
    function getListProducts($id) {
        $u = new seTable('shop_discount_links','sdl');
        $u->select('sp.id, sp.code, sp.article, sp.name, sp.price, sp.curr');
        $u->innerjoin("shop_price sp", "sdl.id_price = sp.id");
        $u->where("sdl.discount_id = $id");
        $u->groupby("sp.id");
        $objects = $u->getList();
        $result = array();
        foreach($objects as $item) {
            $product = null;
            $product['id'] = $item['id'];
            $product['name'] = $item['name'];
            $product['code'] = $item['code'];
            $product['article'] = $item['article'];
            $product['price'] = (real) $item['price'];
            $product['currency'] = $item['curr'];
            $result[] = $product;
        }
        return $result;
    }

    function getListGroupsProducts($id) {
        $u = new seTable('shop_discount_links','sdl');
        $u->select('sg.id, sg.code_gr, sg.name');
        $u->innerjoin("shop_group sg", "sdl.id_group = sg.id");
        $u->where("sdl.discount_id = $id");
        $u->groupby("sg.id");
        $objects = $u->getList();
        $result = array();
        foreach($objects as $item) {
            $group = null;
            $group['id'] = $item['id'];
            $group['name'] = $item['name'];
            $group['code'] = $item['code_gr'];
            $result[] = $group;
        }
        return $result;
    }

    function getListModificationsProducts($id) {
        $u = new seTable('shop_discount_links','sdl');
        $u->select('sm.id, sm.value AS `price_modification`, sp.curr, sp.price,
            CONCAT(sp.name, "(", GROUP_CONCAT(sfvl.value SEPARATOR ","), ")") AS `name`');
        $u->innerjoin('shop_modifications sm', 'sdl.id_modification = sm.id');
        $u->innerjoin('shop_price sp', 'sm.id_price = sp.id');
        $u->innerjoin('shop_modifications_feature smf', 'smf.id_modification = sm.id');
        $u->innerjoin('shop_feature_value_list sfvl', 'sfvl.id = smf.id_value');
        $u->where("sdl.discount_id = $id");
        $u->groupby('sm.id');
        $objects = $u->getList();
        $result = array();
        foreach($objects as $item) {
            $product = null;
            $product['id'] = $item['id'];
            $product['name'] = $item['name'];
            if ($item['price_modification'] === 0)
                $product['price'] = (real) $item['price'];
            else $product['price'] = (real) $item['price_modification'];
            $product['currency'] = $item['curr'];
            $result[] = $product;
        }
        return $result;
    }

    function getListContacts($id) {
        $u = new seTable('shop_discount_links','sdl');
        $u->select('p.id, p.first_name, p.sec_name, p.last_name, p.email');
        $u->innerjoin("person p", "sdl.id_user = p.id");
        $u->where("sdl.discount_id = $id");
        $u->groupby("p.id");
        $objects = $u->getList();
        $result = array();
        foreach($objects as $item) {
            $contact = null;
            $contact['id'] = $item['id'];
            $contact['firstName'] = $item['first_name'];
            $contact['secondName'] = $item['sec_name'];
            $contact['lastName'] = $item['last_name'];
            $contact['email'] = $item['email'];
            $result[] = $contact;
        }
        return $result;
    }


    if (empty($json->ids))
        $json->ids[] = $_GET['id'];
    $ids = implode(",", $json->ids);

    $u = new seTable('shop_discounts','sd');
    $u->where("sd.id in ($ids)");
    $result = $u->getList();

    $status = array();
    $items = array();

    foreach($result as $item) {
        $discount = null;
        $discount['id'] = $item['id'];
        $discount['name'] = $item['title'];
        $discount['stepTime'] = (int) $item['step_time'];
        $discount['stepDiscount'] = (float) $item['step_discount'];
        $discount['dateTimeFrom'] = $item['date_from'];
        $discount['dateTimeTo'] = $item['date_to'];
        $discount['week'] = $item['week'];
        $discount['sumFrom'] = (float) $item['summ_from'];
        $discount['sumTo'] = (float) $item['summ_to'];
        $discount['countFrom'] = (float) $item['count_from'];
        if  ($discount['countFrom'] < 0)
            $discount['countFrom'] = 0;
        $discount['countTo'] = (float) $item['count_to'];
        if  ($discount['countTo'] < 0)
            $discount['countTo'] = 0;
        $discount['discount'] = (float) $item['discount'];
        $discount['typeDiscount'] = $item['type_discount'];
        $discount['typeSum'] = (int) $item['summ_type'];
        $discount['listGroupsProducts'] = getListGroupsProducts($discount['id']);
        $discount['listProducts'] = getListProducts($discount['id']);
        $discount['listModificationsProducts'] = getListModificationsProducts($discount['id']);
        $discount['listContacts'] = getListContacts($discount['id']);
        $items[] = $discount;
    }

    $data['count'] = sizeof($items);
    $data['items'] = $items;

    if (se_db_error()) {
        $status['status'] = 'error';
        $status['errortext'] = se_db_error();
    } else {
        $status['status'] = 'ok';
        $status['data'] = $data;
    }

    outputData($status);