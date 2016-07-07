<?php

// преоброзование переменных запроса в перемнные БД
function convertFields($str) {
    $str = str_replace('idRole', 'por.id_role', $str);
    return $str;
}

$u = new seTable('permission_object','po');
$u->select('po.*, por.id id_permission, por.id_role, por.mask');
$u->leftjoin('permission_object_role por', 'por.id_object = po.id');
$u->groupby('po.id');
$u->orderby('name');
if (!empty($json->filter))
    $u->where(convertFields($json->filter));

$count = $u->getListCount();
if (!$count) {
    $u = new seTable('permission_object','po');
    $u->groupby('id');
    $u->orderby('name');
}

$objects = $u->getList();
foreach($objects as $item) {
    $object = null;
    $object['id'] = $item['id_permission'];
    $object['idObject'] = $item['id'];
    $object['idRole'] = $item['id_role'];
    $object['code'] = $item['code'];
    $object['name'] = $item['name'];
    $object['mask'] = (int) $item['mask'];

    $items[] = $object;
}

$data['count'] = $count;
$data['items'] = $items;

$status = array();
if (!se_db_error()) {
    $status['status'] = 'ok';
    $status['data'] = $data;
} else {
    $status['status'] = 'error';
    $status['errortext'] = 'Не удаётся прочитать список объектов прав!';
}

outputData($status);