<?php


function convertFields($str)
{
    $str = str_replace('id ', 'sp.id ', $str);
    return $str;
}

$u = new seTable('shop_credit', 'sc');
$u->select('sc.*, scs.name request_status,
      (SELECT (SUM((sci.price - IFNULL(sci.discount, 0)) * sci.count)) 
          FROM shop_credit_item sci WHERE sci.id_credit = sc.id) sum');
$u->leftJoin("shop_credit_status scs", "sc.id_status = scs.id");

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
    $order['dateOrder'] =  date("Y-m-d", strtotime($item['date']));
    $order['customer'] = $item['fio'];
    $order['customerPhone'] = $item['phone'];
    $order['customerINN'] = $item['inn'];
    $order['note'] = $item['commentary'];
    $order["requestStatus"] = $item["request_status"];
    $order["isSent"] = (bool) $item["status"] == 1;
    $order['sum'] = (real) $item['sum'];

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
    $status['error'] = 'Не удаётся получить список заявок!';
}

outputData($status);