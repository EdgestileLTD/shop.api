<?php
    session_start();

    $serial = $_POST["serial"];
    $hash = $_POST["hash"];

    $answer = file_get_contents("https://api.siteedit.ru/api/1/Auth/Register.api?serial=$serial&hash=$hash");
    $json = json_decode($answer);
    $_SESSION['apiUrl'] = $json->data->uri;
    $_SESSION['apiToken'] = $json->data->token;

    echo $answer;