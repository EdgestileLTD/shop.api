<?php

// преоброзование переменных запроса в перемнные БД
function convertFields($str)
{
    $str = str_replace('id ', 'p.id ', $str);
    $str = str_replace('title', 'last_name', $str);
    $str = str_replace('firstName', 'first_name', $str);
    $str = str_replace('lastName', 'last_name', $str);
    $str = str_replace('secondName', 'sec_name', $str);
    $str = str_replace('regDate', 'reg_date', $str);
    $str = str_replace('phone', 'phone', $str);
    $str = str_replace('email', 'email', $str);
    $str = str_replace('isRead', 'is_read', $str);
    $str = str_replace('idGroup', 'sug.group_id', $str);
    $str = str_replace('emailValid', 'email_valid', $str);
    $str = str_replace('display', 'last_name', $str);
    $str = str_replace('login', 'su.username', $str);
    $str = str_replace('company', 'c.name', $str);
    $str = str_replace('idManager', 'p.manager_id', $str);
    return $str;
}

//Автокорректировщик полей ----------------------------------------------
se_db_add_field('person', 'email_valid', "enum('Y','N','C') DEFAULT 'C'");
se_db_add_index('person', 'email_valid', 1);
se_db_add_index('person', 'created_at', 1);
//-----------------------------------------------------------------------
$u = new seTable('person', 'p');
$u->select('p.*, count(so.id) as countorders, GROUP_CONCAT(sug.group_id SEPARATOR ";") AS idsGroups,
            su.username, su.password, su.is_active, c.name company');
$u->innerjoin('se_user su', 'p.id = su.id');
$u->leftjoin('shop_order so', 'so.id_author = p.id AND is_delete="N"');
$u->leftjoin('se_user_group sug', 'p.id = sug.user_id');
$u->leftjoin('company_person cp', 'cp.id_person = p.id');
$u->leftjoin('company c', 'c.id = cp.id_company');

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
            $search .= "(p.last_name like '%$searchItem%'
                        OR p.sec_name like '%$searchItem%'
                        OR p.first_name like '%$searchItem%'
                        OR p.email like '%$searchItem%' OR su.username like '%$searchItem%')";
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

if ($json->sortBy == 'title') {
    $u->orderby('last_name', $json->sortOrder === 'desc');
    $u->addorderby('first_name', $json->sortOrder === 'desc');
} else
    $json->sortBy = convertFields($json->sortBy);

if ($json->sortBy)
    $u->orderby($json->sortBy, $json->sortOrder === 'desc');

$count = $u->getListCount();
$result = $u->getList($json->offset, $json->limit);
foreach ($result as $item) {
    $contact = null;
    $contact['id'] = $item['id'];
    $contact['isActive'] = $item['is_active'] == 'Y';
    $contact['login'] = $item['username'];
    $contact['regDate'] = date('Y-m-d', strtotime($item['reg_date']));
    $contact['regDateTitle'] = date('d.m.Y', strtotime($item['reg_date']));
    $contact['regDateTime'] = $item['reg_date'];
    $contact['firstName'] = $item['first_name'];
    $contact['secondName'] = $item['sec_name'];
    $contact['lastName'] = $item['last_name'];
    $contact['company'] = $item['company'];
    $contact['title'] = $item['last_name'] . ' ' . $item['first_name'] . ' ' . $item['sec_name'];
    $contact['email'] = $item['email'];
    $contact['phone'] = correctInfoPhone($item['phone']);
    $contact['countOrders'] = $item['countorders'];
    $contact['note'] = $item['note'];
    $contact['country'] = $item['country'];
    $contact['imageFile'] = $item['avatar'];
    $contact['priceType'] = (int) $item['price_type'];
    $contact['emailValid'] = (isset($item['email_valid'])) ? $item['email_valid'] : 'C';
    $idsGroups = explode(';', $item['idsGroups']);
    foreach ($idsGroups as $idGroup)
        $contact['idsGroups'][] = $idGroup;
    $items[] = $contact;
}

$data['count'] = $count;
$data['items'] = $items;

$status = array();
if (!se_db_error()) {
    $status['status'] = 'ok';
    $status['data'] = $data;
} else {
    $status['status'] = 'error';
    $status['error'] = 'Не удаётся получить контакт!';
}
outputData($status);