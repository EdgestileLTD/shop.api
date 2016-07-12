<?php

define("CRYPT_KEY", "7YyDfvsH5mfdu8zkFppYczgMmpXWgBf08kmeT3xluEt4BTW1oIK6zCyvJhNTPYUi");

function cryptDecodeStr($encrypted)
{
    $td = mcrypt_module_open(MCRYPT_BLOWFISH, '', 'cbc', '');
    $iv = 'SiteEdit';
    mcrypt_generic_init($td, CRYPT_KEY, $iv);
    $decrypted = mdecrypt_generic($td, base64_decode($encrypted));
    mcrypt_generic_deinit($td);
    mcrypt_module_close($td);
    return $decrypted;
}

function writeLog($data)
{
    if (!is_string($data))
        $data = print_r($data, 1);
    $file = fopen($_SERVER['DOCUMENT_ROOT'] . "/api/debug.log", "a+");
    $query = "$data" . "\n";
    fputs($file, $query);
    fclose($file);
}

$headers = getallheaders();
if (!empty($headers['Secookie']))
    session_id($headers['Secookie']);
session_start();

chdir($_SERVER['DOCUMENT_ROOT']);
date_default_timezone_set("Europe/Moscow");
ini_set('display_errors', 0);
error_reporting(E_ALL);

define('API_VERSION', 5);
define('IS_EXT', file_exists($_SERVER['DOCUMENT_ROOT'] . '/system/main/init.php'));
define('API_ROOT', $_SERVER['DOCUMENT_ROOT'] . '/api/' . API_VERSION . '/');
define('API_ROOT_URL', "http://" . $_SERVER['SERVER_NAME'] . "/api/" . API_VERSION);

if (IS_EXT) {
    define('SE_INDEX_INCLUDED', true);
    require_once 'system/main/init.php';
    require_once 'api/update.php';
} else {
    require_once '/home/e/edgestile/admin/home/siteedit/lib/function.php';
    require_once '/home/e/edgestile/admin/home/siteedit/lib/lib_function.php';
}

require_once API_ROOT . "vendor/autoload.php";

$apiMethod = $_SERVER['REQUEST_METHOD'];
$apiClass = parse_url($_SERVER["REQUEST_URI"]);
$apiClass = str_replace("api/" . API_VERSION . "/", "", trim($apiClass['path'], "/"));
$origin = !empty($headers['Origin']) ? $headers['Origin'] : $headers['origin'];

if (!empty($origin)) {
    $url = parse_url($origin);
    if ($url) {
        if ($url['host'] == 'shop.siteedit24.com')
            header("Access-Control-Allow-Origin: http://shop.siteedit24.com");
        if ($url['host'] == 'localhost' && $url['port'] == 1337)
            header("Access-Control-Allow-Origin: http://localhost:1337");
        header("Access-Control-Allow-Credentials: true");
        header("Access-Control-Allow-Headers: Token, Secookie");
        header("Access-Control-Allow-Methods: FETCH, POST, DELETE, SAVE, INFO, GET, ADDPRICE, TRANSLIT, UPLOAD, CHECKNAMES, SORT");
    }
    if ($apiMethod == "OPTIONS")
        exit;
}

if ($apiClass == "Auth" && strtolower($apiMethod) == "get") {
    if (empty($_SESSION['isAuth'])) {
        header("HTTP/1.1 401 Unauthorized");
        echo 'Сессия истекла! Необходима авторизация!';
        exit;
    }
    exit;
}

$token = $headers['Token'];
$authLogin = $headers['Login'];
$authPassword = $headers['Password'];
$phpInput = file_get_contents('php://input');

if ($token) {
    list($hostname, $token) = explode('||', cryptDecodeStr($token));
    define("TOKEN", $token);
    define("HOSTNAME", $hostname);
    define('DOCUMENT_ROOT', IS_EXT ? $_SERVER['DOCUMENT_ROOT'] : '/home/e/edgestile/' . HOSTNAME . '/public_html');
    $dbConfig = DOCUMENT_ROOT . '/system/config_db.php';
    if (file_exists($dbConfig))
        require_once $dbConfig;
    else {
        header("HTTP/1.1 500 Internal Server Error");
        echo 'Отсутствует конфигурация базы данных!';
        exit;
    }
    $coreVersion = "5.1";
    $verFile = IS_EXT ? 'lib/version' : PATH_ROOT . $json->hostname . '/public_html/lib/version';
    if (file_exists($ver_file)) {
        $coreVersion = trim(file_get_contents($ver_file));
        $coreVersion = explode(':', $coreVersion);        
        $coreVersion = $coreVersion[1];
    }
    define('CORE_VERSION', $coreVersion);
} else {
    header("HTTP/1.1 401 Unauthorized");
    echo 'Отсутствует токен авторизации!';
    exit;
}

if ($apiClass != "Auth" && empty($_SESSION['isAuth']) && !in_array($_SERVER["REMOTE_ADDR"], $allowableServers)) {
    header("HTTP/1.1 401 Unauthorized");
    echo 'Необходима авторизация!';
    exit;
}

$apiObject = $apiClass;
if (!class_exists($apiClass = "\\SE\\Shop\\" . str_replace("/", "\\", $apiClass))) {    
    header("HTTP/1.1 404 Not found");
    echo "Объект '{$apiObject}' не найден!";
    exit;
}
if (!method_exists($apiClass, $apiMethod)) {
    header("HTTP/1.1 404 Not found");
    echo "Метод'{$apiMethod}' не поддерживается!";
    exit;
}
$apiObject = new $apiClass($phpInput);
if ($apiObject->initConnection($CONFIG))
    $apiObject->$apiMethod();
$apiObject->output();
