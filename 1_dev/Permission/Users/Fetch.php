<?php

// преоброзование переменных запроса в перемнные БД
function convertFields($str) {
    $str = str_replace('idRole', 'pru.id_role', $str);
    return $str;
}

$u = new seTable('se_user', 'su');
$u->select('su.id, p.reg_date, p.first_name, p.sec_name, p.last_name, su.username, su.is_active, su.is_super_admin,
    GROUP_CONCAT(pru.id_role SEPARATOR ",") idsRoles,
    GROUP_CONCAT(pr.name ORDER BY pr.name SEPARATOR ", ") roles');
$u->innerjoin('person p', 'p.id = su.id');
$u->leftjoin('permission_role_user pru', 'pru.id_user = su.id');
$u->leftjoin('permission_role pr', 'pr.id = pru.id_role');
$u->where('su.is_manager');
$u->orderby('su.id');
$u->groupby('su.id');

if (!empty($json->filter))
    $u->andWhere(convertFields($json->filter));

$count = $u->getListCount();
$result = $u->getList();
foreach ($result as $item) {
    $manager = null;
    $manager['id'] = $item['id'];
    $manager['isActive'] = $item['is_active'] == 'Y';
    $manager['regDate'] = date('Y-m-d', strtotime($item['reg_date']));
    $manager['firstName'] = $item['first_name'];
    $manager['secondName'] = $item['sec_name'];
    $manager['lastName'] = $item['last_name'];
    $manager['login'] = $item['username'];
    $manager['title'] = $item['last_name'] . ' ' . $item['first_name'] . ' ' . $item['sec_name'];
    $manager['idsRoles'] = $item['idsRoles'];
    $manager['roles'] = $item['roles'];
    $items[] = $manager;
}

$status = array();
if (!se_db_error()) {
    $status['status'] = 'ok';
    $status['data'] = array('count' => count($items), 'items' => $items);
} else {
    $status['status'] = 'error';
    $status['error'] = 'Не удаётся получить список пользователей!';
}
outputData($status);