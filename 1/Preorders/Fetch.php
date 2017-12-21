<?php

function convertFields($str)
{
    $str = str_replace('id ', 'sp.id ', $str);
    return $str;
}

$u = new seTable('shop_preorder', 'sp');
$u->select('sp.*, p.name product_name');
$u->innerJoin("shop_price p", "sp.id_price = p.id");

if (!empty($json->filter))
    $filter = convertFields($json->filter);
if (!empty($filter))
    $where = $filter;
if (!empty($where))
    $where = "(" . $where . ")";
if (!empty($where))
    $u->where($where);
$u->groupby('id');

$json->sortBy = convertFields($json->sortBy);
if ($json->sortBy)
    $u->orderby($json->sortBy, $json->sortOrder === 'desc');

$count = $u->getListCount();
$objects = $u->getList($json->offset, $json->limit);
foreach ($objects as $item) {
    $order = null;
    $order['id'] = $item['id'];
    $order['dateOrder'] =  date("Y-m-d", strtotime($item['created_at']));;
    $order['idProduct'] = $item['id_price'];
    $order['customer'] = $item['name'];
    $order['customerEmail'] = $item['email'];
    $order['customerPhone'] = $item['phone'];
    $order['productName'] = $item['product_name'];
    $order['count'] = (real) $item['count'];
    $order['isSent'] = (bool) $item['send_mail'];

    $items[] = $order;
}

$data['count'] = $count;
$data['items'] = $items;

$status = array();
if (!se_db_error()) {
    $status['status'] = 'ok';
    $status['data'] = $data;
} else {
    $status['status'] = 'error';
    $status['error'] = 'Не удаётся получить список предзаказов!';
}

outputData($status);