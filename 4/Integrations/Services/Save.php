<?php

$ids = array();
if (empty($json->ids) && !empty($json->id))
    $ids[] = $json->id;
else $ids = $json->ids;

if (empty($ids)) {
    $u = new seTable('integration', 'i');
    $u->select('i.id, io.id idOAuth');
    $u->leftjoin('integration_oauth io', 'io.id_integration = i.id');
    $u->where('i.id=?', 1);
    $result = $u->getList();
    $integration = array();
    foreach ($result as $item) {
        $integration = $item;
        break;
    }

    $isNew = empty($integration['idOAuth']);
    $u = new seTable('integration_oauth', 'io');
    setField($isNew, $u, $integration['id'], 'id_integration');
    setField($isNew, $u, $json->access_token, 'token');
    setField($isNew, $u, $json->ya_login, 'login');
    setField($isNew, $u, date("Y-m-d H:i:s", time() + $json->expires_in), 'expired');
    if (!empty($integration['idOAuth']))
        $u->where('id=?', $integration['idOAuth']);
    $u->save();

    $_SESSION['tokenYandex'] = $json->access_token;
    $_SESSION['loginYandex'] = $json->ya_login;

} else {
    $u = new seTable('integration', 'i');
    setField($isNew, $u, $json->isActive, 'is_active');
    $u->where('id=?', $ids[0]);
    $u->save();
}

$data['id'] = 1;
$status = array();
if (!se_db_error()) {
    $status['status'] = 'ok';
    $status['data'] = $data;
} else {
    $status['status'] = 'error';
    $status['errortext'] = 'Не удаётся сохранить информацию о сервисе!';
}

outputData($status);
