<?php

require_once "config.php";

header('Location: https://oauth.yandex.ru/authorize?response_type=code&client_id=' . CLIENT_ID . '&state=' . $_GET['token']);
