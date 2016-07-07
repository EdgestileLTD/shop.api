<?php

$patterns = array('id' => 'sс.id',
    'dateComment' => 'sc.date',
    'contactTitle' => 'sc.name',
    'nameProduct' => 'sp.name',
    'commentary' => 'commentary',
    'response' => 'response'
);

$sortBy = (isset($patterns[$json->sortBy])) ? $patterns[$json->sortBy] : 'id';

$u = new seTable('shop_comm', 'sc');
$u->select('sc.*, sp.id as idproduct, sp.name as nameproduct');
$u->innerjoin('shop_price sp', 'sp.id = sc.id_price');

if ($json->sortOrder == 'desc')
    $u->orderby($sortBy, 1);
else $u->orderby($sortBy, 0);

$objects = $u->getList();
foreach ($objects as $item) {
    $comm = null;
    $comm['id'] = $item['id'];
    $comm['date'] = date('d.m.Y', strtotime($item['date']));
    $comm['idProduct'] = $item['idproduct'];
    $comm['nameProduct'] = $item['nameproduct'];
    $comm['contactTitle'] = $item['name'];
    $comm['contactEmail'] = $item['email'];
    $comm['commentary'] = $item['commentary'];
    $comm['response'] = $item['response'];
    $comm['isShowing'] = $item['showing'] == 'Y';
    $comm['isActive'] = $item['is_active'] == "Y";
    $items[] = $comm;
}

$data['count'] = sizeof($items);
$data['items'] = $items;

$status = array();
if (!se_db_error()) {
    $status['status'] = 'ok';
    $status['data'] = $data;
} else {
    $status['status'] = 'error';
    $status['errortext'] = 'Не удаётся прочитать список комментариев!';
}
outputData($status);