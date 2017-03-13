<?php

$u = new seTable('integration','i');
$u->select('i.id, i.name, url_oauth, url_api, io.token, io.login, io.expired, i.is_active');
$u->leftjoin('integration_oauth io', 'io.id_integration = i.id');
$objects = $u->getList();

$items = array();
foreach($objects as $item) {
    $integration = null;
    $token = session_id();
    $integration['id'] = $item['id'];
    $integration['name'] = $item['name'];
    $integration['urlOAuth'] = $item['url_oauth'] . "?token={$token}";
    $integration['urlApi'] = $item['url_api'];
    $integration['token'] = $item['token'];
    $integration['login'] = $item['login'];
    $integration['expired'] = date('d.m.Y', strtotime($item['expired']));
    $integration['isActive'] = (bool) $item['is_active'];
    $items[] = $integration;
}

$data['count'] = sizeof($items);
$data['items'] = $items;

$status = array();
if (!se_db_error()) {
    $status['status'] = 'ok';
    $status['data'] = $data;
} else {
    $status['status'] = 'error';
    $status['error'] = 'Не удаётся получить список сервисов для интеграции!';
}

outputData($status);

