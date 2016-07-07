<?php

require_once "config.php";

define('API_ROOT_URL', "http://" . $_SERVER['SERVER_NAME'] . "/api/1");

if (isset($_GET['code'])) {
    $ya_code = $_GET['code'];
    $ya_state = urldecode($_GET['state']);

    $uri_api = 'http://api-fotki.yandex.ru';

    $params = array(
        'grant_type' => 'authorization_code',
        'code' => $_GET['code'],
        'client_id' => CLIENT_ID,
        'client_secret' => CLIENT_SECRET
    );

    // получение токена OAUTH
    $url = 'https://oauth.yandex.ru/token';
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_POST, 1);
    curl_setopt($curl, CURLOPT_POSTFIELDS, urldecode(http_build_query($params)));
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
    $data = json_decode(curl_exec($curl), true);
    curl_close($curl);

    // получение логина пользователя
    $url =  $uri_api . '/api/me/';
    $curl = curl_init();
    $header[] = 'Host: api-fotki.yandex.ru';
    $header[] = 'Authorization: OAuth ' . $data['access_token'];
    curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
    curl_setopt($curl, CURLOPT_HEADER, 1);
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    $result = curl_exec($curl);
    $isErrorLogin = false;
    if (strpos($result, "Wrong Authorization header value") === false) {
        $str_location = 'Location:';
        $location = trim(substr($result, $p = strpos($result, $str_location) + strlen($str_location), strlen($result) - $p));
        $login = str_replace("/", "", substr($location, $p = strpos($location, 'users/') + strlen('users/')));
        $data['ya_login'] = $login;
    } else $isErrorLogin = true;
    $url = API_ROOT_URL . "/Integrations/Services/Save.api";
    $ch = curl_init($url);

    curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type: application/json", "Secookie: {$ya_state}"));
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $result = json_decode(curl_exec($ch));
}

?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <META NAME="webmoney.attestation.label" CONTENT="webmoney attestation label#5D069E14-C9B1-455C-B93A-2B59716439BF"/>
    <title>SiteEdit24</title>
    <link href='https://fonts.googleapis.com/css?family=Open+Sans:400,300,600,700|Roboto&subset=latin,cyrillic'
          rel='stylesheet' type='text/css'>
</head>

<body>
    <?php if (!$isErrorLogin): ?>
        <h3>Токен получен. Вы можете вернуться в приложение!</h3>
    <?php else: ?>
        <h3>Не удаётся получить токен!</h3>
    <?php endif; ?>
</body>
</html>

