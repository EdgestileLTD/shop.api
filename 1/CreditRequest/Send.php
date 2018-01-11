<?php

$params = array(
    'token' => md5($CONFIG['DBSerial'] . $CONFIG['DBPassword']),
    'id_application' => $json->id,
    'action' => 'SendApplication'
);

$urlSend = 'http://' . $json->hostname . '/upload/homecredit.php?' . http_build_query($params);

$result = file_get_contents($urlSend, false, stream_context_create(array(
    'http' => array(
        'method' => 'POST',
        'header' => 'Content-type: application/x-www-form-urlencoded',
        'content' => http_build_query($params)
    )
)));

if ($result == "ok")
    $status['status'] = 'ok';
else {
    $status['status'] = 'error';
    $status['error'] = 'Не удаётся отправить заявку в банк!';
}

outputData($status);