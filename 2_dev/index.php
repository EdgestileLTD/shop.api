<?php

$allowedMethods = array('FETCH', 'POST', 'DELETE', 'SAVE', 'INFO', 'GET', 'ADDPRICE', 'TRANSLIT', 'UPLOAD',
    'CHECKNAMES', 'SORT', 'EXPORT', 'IMPORT', 'LOGOUT', 'ITEMS', 'STORE', 'CONVERT');
$allowedMethods = implode(",", $allowedMethods);

$headers = getallheaders();
if (!empty($headers['Secookie']))
    session_id($headers['Secookie']);
session_start();

chdir($_SERVER['DOCUMENT_ROOT']);
date_default_timezone_set("Europe/Moscow");
ini_set('display_errors', 0);
error_reporting(E_ALL);

define('API_VERSION', "2_dev");
define('IS_EXT', file_exists($_SERVER['DOCUMENT_ROOT'] . '/system/main/init.php'));
define('API_ROOT', $_SERVER['DOCUMENT_ROOT'] . '/api/' . API_VERSION . '/');
define('API_ROOT_URL', "http://" . $_SERVER['SERVER_NAME'] . "/api/" . API_VERSION);
define('AUTH_SERVER', "https://api.siteedit.ru");
define('URL_API_ORIGINAL', 'http://upload.beget.edgestile.net/api');

/**
 * запись в логи
 *
 * @param $data              текст
 * @param bool $enter        переключение печати в строчкУ/строчкИ (по умолчанию с новой строчки)
 * @var TYPE_NAME $date      [TimeNow]
 * @var TYPE_NAME $interval  SI - secInterval (прошло секунд с последнего лога в пределах минуты)
 */
function writeLog($data, $enter = TRUE)
{
    if (!is_string($data))
        $data = print_r($data, 1);
    $file = fopen($_SERVER['DOCUMENT_ROOT'] . "/api/" . API_VERSION ."/debug.log", "a+");
    $date = microtime();
    $date = explode(" ",$date);
    $date = strval($date[0]);

    $dateLogMin = (int)(date("i"));
    $dateLogSec = substr($date, 2);
    $dateLogSec = (float)(date("s") . ".$dateLogSec");
    $interval = (float)$dateLogSec - (float)$_SESSION["logInterval"]['sec'];
    $interval = number_format($interval,6, '.','');
    $interval = '[SI ' . $interval . ']';

    $date = substr($date, 2, 3);
    $date = '['.date("H:i:s").":$date]";
    $intervalMin = $dateLogMin - $_SESSION["logInterval"]['min'];
    if ($intervalMin == 0) $query = $interval." $data";
    else                   $query = $date." $data";

    if($enter == TRUE) $query = "\n".$query;
    else               $query = "  ".$query;

    $_SESSION["logInterval"] = array(
        'sec' => $dateLogSec,
        'min' => $dateLogMin
    );
    fputs($file, $query);
    fclose($file);
}

// библиотеки
if (IS_EXT) {
    require_once 'api/update.php';
    require_once 'lib/lib_function.php';
    require_once 'lib/lib_se_function.php';
} else {
    require_once '/home/e/edgestile/admin/home/siteedit/lib/function.php';
    require_once '/home/e/edgestile/admin/home/siteedit/lib/lib_function.php';
}

require_once API_ROOT . "../1/version.php";
require_once API_ROOT . "vendor/autoload.php";

/**
 * @var array $apiClass получение из ajax параметра object
 */
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
        header("Access-Control-Allow-Headers: Project, Secookie");
        header("Access-Control-Allow-Methods: $allowedMethods");
    }
    if ($apiMethod == "OPTIONS")
        exit;
}

if (IS_EXT) {
    $originalBuild = (int)file_get_contents(URL_API_ORIGINAL . "/update.php?method=getVersion");
    if ($originalBuild > API_BUILD) {
        $update = new Update(URL_API_ORIGINAL . "/update.php");
        $update->exec();
    }
}

if (strpos($apiClass, "/Auth"))
    $apiClass = "Auth";

if (strpos($apiClass, "/TokenAuth"))
    $apiClass = "TokenAuth";

// сессия сломана
if (($apiClass == "Auth" || $apiClass == "TokenAuth") && strtolower($apiMethod) == "logout") {
    $_SESSION = array();
    session_destroy();
    echo "Session destroy!";
    exit;
}

// проверка авторизации
if (($apiClass == "Auth" || $apiClass == "TokenAuth") && strtolower($apiMethod) == "get") {
    if (empty($_SESSION['isAuth'])) {
        header("HTTP/1.1 401 Unauthorized");
        echo 'Сессия истекла! Необходима авторизация!';
        exit;
    }
}

$phpInput = file_get_contents('php://input');
$hostname = $_SESSION['hostname'];

if (($apiClass == "Auth" || $apiClass == "TokenAuth") && strtolower($apiMethod) == "info")
    $hostname = (strpos($headers["Project"], '.') !== false) ? $headers["Project"] : $headers["Project"] . '.e-stile.ru';

define("HOSTNAME", $hostname);
define('DOCUMENT_ROOT', IS_EXT ? $_SERVER['DOCUMENT_ROOT'] : '/home/e/edgestile/' . HOSTNAME . '/public_html');
$dbConfig = DOCUMENT_ROOT . '/system/config_db.php';
if (file_exists($dbConfig))
    require_once $dbConfig;
else {
    header("HTTP/1.1 401 Unauthorized");
    echo 'Сессия истекла! Необходима авторизация!';
    exit;
}

$dirSettings = DOCUMENT_ROOT . '/manager';
if (!file_exists($dirSettings))
    mkdir($dirSettings);

define("DIR_SETTINGS", $dirSettings);

// проверка авторизации
if (($apiClass != "Auth" && $apiClass != "TokenAuth") && empty($_SESSION['isAuth'])) {
    header("HTTP/1.1 401 Unauthorized");
    echo 'Необходима авторизация!';
    exit;
}

$apiObject = $apiClass;
if (!class_exists($apiClass = "\\SE\\" . str_replace("/", "\\", $apiClass))) {
    header("HTTP/1.1 501 Not Implemented");
    echo "Объект '{$apiObject}' не найден!";
    exit;
}
if (!method_exists($apiClass, $apiMethod)) {
    header("HTTP/1.1 501 Not Implemented");
    echo "Метод'{$apiMethod}' не поддерживается!";
    exit;
}


/**
 * @var array $phpInput получает данные параметра data из ajax
 * @var array $CONFIG получает DBName,HostName,DBUserName,DBPassword,DBDsn,DBSerial
 */
$apiObject = new $apiClass($phpInput);
if ($apiObject->initConnection($CONFIG))
    $apiObject->$apiMethod();
$apiObject->output();
