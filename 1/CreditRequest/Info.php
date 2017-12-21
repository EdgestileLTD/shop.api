<?php

$id = $_GET['id'];
if (!$id) {
    if (empty($json->ids))
        exit;

    if (sizeof($json->ids))
        $id = $json->ids[0];
    else exit;
} else $json->ids[] = $id;

$ids = implode(",", $json->ids);

function getOrderItems($idOrder)
{
    $u = new seTable('shop_credit_item', 'sci');
    $u->select("sci.*");
    $u->where("sci.id_credit = ?", $idOrder);
    $u->groupby('sci.id');
    $result = $u->getList();
    $items = array();
    if (!empty($result)) {
        foreach ($result as $item) {
            $product['id'] = $item['id'];
            $product['idPrice'] = $item['id_price'];
            $product['article'] = $item['article'];
            $product['name'] = $item['name'];
            $product['price'] = (real)$item['price'];
            $product['count'] = (real)$item['count'];
            $product['discount'] = (real)$item['discount'];
            $product['note'] = $item['commentary'];

            $items[] = $product;
        }
    }
    return $items;
}


$u = new seTable('shop_credit', 'sc');
$u->select("sc.*, scs.name request_status");
$u->leftJoin("shop_credit_status scs", "sc.id_status = scs.id");
if (!empty($ids))
    $u->where("sc.id IN (?)", $ids);
else $u->where("sc.id IS NULL");
$u->groupby('sc.id');

$result = $u->getList();

$items = array();
if (!empty($result)) {
    foreach ($result as $item) {
        $order = null;
        $order['id'] = $item['id'];
        $order['dateOrder'] = $item['date_order'];
        $order['customer'] = $item['fio'];
        $order['customerINN'] = $item['inn'];
        $order['customerPhone'] = $item['customerPhone'];
        $order['note'] = $item['commentary'];
        $order["requestStatus"] = $item["request_status"];

        // список товаров
        $order['items'] = getOrderItems($order['id']);

        $items[] = $order;
    }
}

if (!se_db_error()) {
    $status['status'] = 'ok';
    $status['data'] = array('count' => count($items), 'items' => $items);
} else {
    $status['status'] = 'error';
    $status['error'] = 'Не удаётся получить информацию о заявке!';
}

outputData($status);
