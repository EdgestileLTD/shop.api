<?php

function convertFields($str)
{
    $str = str_replace('[id]', 'so.id ', $str);
    $str = str_replace('[idGroup]', 'sp.id_group', $str);
    $str = str_replace('not [isDelete]', '(so.is_delete = "N" OR so.is_delete IS NULL)', $str);
    $str = str_replace('[isDelete]', '(so.is_delete="Y")', $str);
    $str = str_replace('[statusOrder]', 'so.status', $str);
    $str = str_replace('[dateOrder]', 'so.date_order', $str);
    $str = str_replace('[statusDelivery]', 'so.delivery_status', $str);
    $str = str_replace('[idCustomer]', 'p.id ', $str);
    $str = str_replace('[idCompany]', 'c.id ', $str);
    $str = str_replace('[idCoupon]', 'id_coupon', $str);
    $str = str_replace('[idAdmin]', 'id_admin', $str);
    $str = str_replace('idCoupon', 'id_coupon', $str);
    return $str;
}

$u = new seTable('shop_order', 'so');
$u->select('so.*, 
            CONCAT_WS(" ", p.last_name, p.first_name, p.sec_name) as customer,
            CONCAT_WS(" ", pm.last_name, pm.first_name, pm.sec_name) as manager,
            
            p.phone as customerPhone, p.email as customerEmail,
            c.name company, c.phone companyPhone, c.email companyEmail, 
           (SELECT (SUM((sto.price-IFNULL(sto.discount, 0))*sto.count)-IFNULL(so.discount, 0) + IFNULL(so.delivery_payee, 0)) FROM shop_tovarorder sto WHERE sto.id_order = so.id) summ, 
            sp.name_payment paymentTypePrimary,
            spp.name_payment paymentType');
$u->leftjoin('person p', 'p.id = so.id_author');
if (empty($_SESSION["idUser"]) || $_SESSION["isUserAdmin"])
    $u->leftjoin('person pm', 'pm.id = so.id_admin');
else
    $u->leftjoin('person pm', "pm.id = so.id_admin AND so.id_admin = {$_SESSION["idUser"]}");

$u->leftjoin('company c', 'c.id = so.id_company');
$u->leftjoin('shop_order_payee sop', 'sop.id_order = so.id');
$u->leftjoin('shop_payment spp', 'spp.id = sop.payment_type');
$u->leftjoin('shop_payment sp', 'sp.id = so.payment_type');
if (strpos($json->filter, 'idGroup') !== false) {
    $u->innerjoin('shop_price sp', 'sto.id_price=sp.id');
}
if (strpos($json->filter, 'idCoupon') !== false) {
    $u->innerjoin('shop_coupons_history sch', 'sch.id_order=so.id');
}

$search = $where = '';

if (!empty($json->searchText)) {
    $filters = explode(" ", $json->searchText);
    foreach ($filters as $filterItem) {
        if (!empty($search))
            $search .= " AND ";
        $search .= "(p.last_name like '%{$filterItem}%'
                    OR p.sec_name like '%{$filterItem}%'
                    OR p.first_name like '%{$filterItem}%'
                    OR so.id = '{$filterItem}'
                    OR p.phone like '%{$filterItem}%')";
    }
}

if (!empty($json->filter))
    $where = $json->filter;

if (!empty($search)) {
    if (!empty($where))
        $where = "(" . convertFields($where) . ") AND (" . $search . ")";
    else $where = $search;
} else $where = convertFields($where);

if (!empty($where)) {
    $u->where($where);
} else
    $u->where('so.is_delete = "N" OR so.is_delete IS NULL');


$u->groupby('id');
$patterns = array('id' => 'id',
    'dateOrder' => 'date_order',
    'customerPhone' => 'p.phone',
    'customer' => 'p.last_name,p.first_name,p.sec_name',
    'sum' => 'summ',
    'dateDelivery' => 'delivery_date',
    'statusOrder' => 'status',
    'statusDelivery' => 'delivery_status'
);

$amount = 0;
$sumResults = se_db_query('SELECT SUM(summ) total_sum, COUNT(*) total_count FROM(' . $u->getSql() . ') sum_tbl');
if ($sumResults && $row = se_db_fetch_assoc($sumResults)) {
    $amount = (real)$row['total_sum'];
    $count = (int)$row['total_count'];
}

$sortBy = (isset($patterns[$json->sortBy])) ? $patterns[$json->sortBy] : 'id';
$u->orderby($sortBy, $json->sortOrder === 'desc');

$result = $u->getList($json->offset, $json->limit);
unset($u);

$items = array();
if ($count > 0) {
    foreach ($result as $item) {
        $order = array();
        $order['id'] = $item['id'];
        $order['sum'] = (real)$item['summ'];
        $order['isCanceled'] = $item['is_delete'] == 'Y';
        $order['orderName'] = $item['nameitem'];
        $order['dateOrder'] = $item['date_order'];
        if (!empty($item['date_order']))
            $order['dateOrderDisplay'] = date("d.m.Y", strtotime($item['date_order']));;
        $order['datePayee'] = $item['date_payee'];
        $order['idCustomer'] = $item['id_author'];
        $order['idCompany'] = $item['id_company'];
        $order['idManager'] = $item['id_admin'];
        $order['manager'] = $item['manager'];
        $order['deliveryDocNum'] = $item['delivery_doc_num'];
        $order['deliveryDocDate'] = $item['delivery_doc_date'];
        $order['deliveryServiceName'] = $item['delivery_service_name'];
        $order['customer'] = htmlspecialchars_decode($item['company'] ? trim($item['company']) : trim($item['customer']));
        $order['currency'] = $item['curr'];
        $order['customerPhone'] = correctInfoPhone($item['companyPhone'] ? $item['companyPhone'] : $item['customerPhone']);
        $order['customerEmail'] = $item['companyEmail'] ? $item['companyEmail'] : $item['customerEmail'];
        $order['deliveryDate'] = $item['delivery_date'];
        $order['note'] = htmlspecialchars_decode($item['commentary']);
        $order['paymentTypePrimary'] = $item['paymentTypePrimary'];
        if (!empty($item['paymentType']))
            $order['paymentTypePrimary'] = $item['paymentType'];
        $order['statusOrder'] = $item['status'];
        $order['statusDelivery'] = $item['delivery_status'];

        if (!empty($order['deliveryDocNum'])) {
            $order["deliveryDocInfo"] = '№ ' . $order['deliveryDocNum'];
            $order["deliveryDocInfo"] .= ' от ' . date("d.m.Y", strtotime($order['deliveryDocDate']));
            if (!empty($order['deliveryServiceName']))
             $order["deliveryDocInfo"] .= ' (' . $order['deliveryServiceName'] . ')';
        }

        $items[] = $order;
    }
}

if (!se_db_error()) {
    $status['status'] = 'ok';
    $status['data'] = array('count' => $count, 'totalAmount' => $amount, 'items' => $items);
} else {
    $status['status'] = 'error';
    $status['error'] = 'Не удаётся получить список заказов!';
}
outputData($status);
