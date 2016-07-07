<?php

// преоброзование переменных запроса в перемнные БД
function convertFields($str) {
    $str = str_replace('idRole', 'por.id_role', $str);
    return $str;
}

$u = new seTable('permission_object','po');
$u->select('po.*, por.mask');
$u->leftjoin('permission_object_role por', 'por.id_object = po.id');
$u->groupby('po.id');
$u->orderby('name');
if (!empty($json->filter))
    $u->where(convertFields($json->filter));

$count = $u->getListCount();
$objects = $u->getList();
foreach($objects as $item) {
    $object = null;
    $object['id'] = $item['id'];
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