<?php

// преоброзование переменных запроса в перемнные БД
function convertFields($str)
{
    $str = str_replace('id ', 'p.id ', $str);
    $str = str_replace('phone', 'c.phone', $str);
    $str = str_replace('email', 'c.email', $str);
    $str = str_replace('inn', 'c.inn', $str);
    $str = str_replace('idGroup', 'sug.group_id', $str);
    return $str;
}

$u = new seTable('company', 'c');
$u->select('c.*, CONCAT_WS(" ", p.last_name, p.first_name, p.sec_name) contact, p.id id_contact,
            GROUP_CONCAT(sug.group_id SEPARATOR ";") idsGroups');
$u->leftJoin('se_user_group sug', 'c.id = sug.company_id');
$u->leftJoin('company_person cp', 'cp.id_company = c.id');
$u->leftJoin('person p', 'p.id = cp.id_person');

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
    $company['kpp'] = $item['kpp'];
    $company['name'] = $item['name'];
    $company['email'] = $item['email'];
    $company['phone'] = $item['phone'];
    $company['idContact'] = $item['id_contact'];
    $company['contact'] = $item['contact'];
    $company['countOrders'] = $item['count_orders'];
    $company['note'] = $item['note'];
    $idsGroups = explode(';', $item['idsGroups']);
    foreach ($idsGroups as $idGroup)
        $company['idsGroups'][] = $idGroup;
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
    $status['error'] = 'Не удаётся получить список компаний!';
}

outputData($status);