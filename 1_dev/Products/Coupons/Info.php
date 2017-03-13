<?php

function getProducts($id) {
    $u = new seTable('shop_coupons_goods','scg');
    $u->select('sp.id, sp.code, sp.article, sp.name, sp.price, sp.curr, sp.measure, sp.presence_count');
    $u->innerjoin("shop_price sp", "scg.price_id = sp.id");
    $u->where("scg.coupon_id=?", $id);
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
        $product['measurement'] = $item['measure'];
        $product['isInfinitely'] = true;
        if ($item['presence_count'] && $item['presence_count'] >= 0) {
            $product['count'] = (float) $item['presence_count'];
            $product['isInfinitely'] = false;
        }
        $result[] = $product;
    }
    return $result;
}

function getGroups($id) {
    $u = new seTable('shop_coupons_goods','scg');
    $u->select('sg.id, sg.name');
    $u->innerjoin("shop_group sg", "scg.group_id = sg.id");
    $u->where("scg.coupon_id=?", $id);
    $u->groupby("sg.id");
    $objects = $u->getList();

    $result = array();
    foreach($objects as $item) {
        $group = null;
        $group['id'] = $item['id'];
        $group['name'] = $item['name'];
        $result[] = $group;
    }
    return $result;
}

if (empty($json->ids))
    exit;

$ids = implode(",", $json->ids);

$u = new seTable('shop_coupons','sc');
$u->select('sc.*, CONCAT_WS(" ",  p.last_name, p.first_name, p.sec_name) as userName');
$u->leftjoin('person p', 'p.id=sc.id_user');
$u->where("sc.id in ($ids)");
$result = $u->getList();

$status = array();
$items = array();

foreach($result as $item) {
    $coupon = null;
    $coupon['id'] = $item['id'];
    $coupon['idUser'] = $item['id_user'];
    $coupon['userName'] = $item['userName'];
    $coupon['code'] = $item['code'];
    $coupon['type'] = $item['type'];
    $coupon['discount'] = (real) $item['discount'];
    $coupon['currencyCode'] = $item['currency'];
    $coupon['timeEnd'] = $item['expire_date'];
    $coupon['minSum'] = (float) $item['min_sum_order'];
    $coupon['isActive'] = (bool) ($item['status'] == "Y");
    $coupon['count'] = (int) $item['count_used'];
    $coupon['isRegUser'] = (bool) ($item['only_registered'] == "Y");
    $coupon['products'] = getProducts($item['id']);
    $coupon['groups'] = getGroups($item['id']);

    $items[] = $coupon;
}

$data['count'] = sizeof($items);
$data['items'] = $items;

if (se_db_error()) {
    $status['status'] = 'error';
    $status['error'] = 'Не удаётся получить информацию о купоне!';
} else {
    $status['status'] = 'ok';
    $status['data'] = $data;
}

outputData($status);