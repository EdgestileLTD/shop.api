<?php

// преоброзование переменных запроса в перемнные БД
function convertFields($str)
{
    $str = str_replace('id ', 'p.id ', $str);
    $str = str_replace('phone', 'c.phone', $str);
    $str = str_replace('email', 'c.email', $str);
    $str = str_replace('inn', 'c.inn', $str);
    return $str;
}

$u = new seTable('company', 'c');
$u->select('c.*');

if (!empty($json->filter))
    $filter = convertFields($json->filter);

$searchStr = $json->searchText;
$searchArr = explode(' ', $searchStr);

if (!empty($searchStr)) {
    if (strpos($searchStr, '?') === 0) {
        $search = substr($searchStr, 1);
        $search = convertFields($search);
    } else {
        foreach ($searchArr as $searchItem) {
            if (!empty($search))
                $search .= " AND ";
            $search .= "(c.name like '%$searchItem%'
                        OR c.fullname like '%$searchItem%'
                        OR c.inn like '%$searchItem%'
                        OR c.email like '%$searchItem%' OR c.phone like '%$searchItem%')";
        }
    }
}
if (!empty($filter))
    $where = $filter;
if (!empty($search)) {
    if (!empty($where))
        $where = "(" . $where . ") AND (" . $search . ")";
    else $where = $search;
}

if (!empty($where))
    $u->where($where);
$u->groupby('id');
$json->sortBy = convertFields($json->sortBy);

if ($json->sortBy)
    $u->orderby($json->sortBy, $json->sortOrder === 'desc');

$count = $u->getListCount();
$result = $u->getList($json->offset, $json->limit);
foreach ($result as $item) {
    $company = null;
    $company['id'] = $item['id'];
    $company['regDate'] = date('Y-m-d', strtotime($item['reg_date']));
    $company['inn'] = $item['inn'];
    $company['name'] = $item['name'];
    $company['email'] = $item['email'];
    $company['phone'] = $item['phone'];
    $company['countOrders'] = $item['count_orders'];
    $company['note'] = $item['note'];
    $items[] = $company;
}

$data['count'] = $count;
$data['items'] = $items;

$status = array();
if (!se_db_error()) {
    $status['status'] = 'ok';
    $status['data'] = $data;
} else {
    $status['status'] = 'error';
    $status['errortext'] = 'Не удаётся получить список компаний!';
}

outputData($status);