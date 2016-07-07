<?php

header("Content-type: text/html;charset=utf-8");

function writeLog($data)
{
    if (!is_string($data))
        $data = print_r($data, 1);

    $file = fopen($_SERVER['DOCUMENT_ROOT'] . "/api/logsql.txt", "a+");
    $query = "$data" . "\n";
    fputs($file, $query);
    fclose($file);
}

date_default_timezone_set("Europe/Moscow");
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '/admin/home/siteedit/config/config.php';
require_once '/admin/home/siteedit/lib/function.php';
require_once '/admin/home/siteedit/lib/lib_function.php';


define('PATH_ROOT', '/home/e/edgestile/');
define('API_VERSION', '2');
define('API_ROOT', $_SERVER['DOCUMENT_ROOT'] . '/api/' . API_VERSION . '/');
define('SALT', 'c635aefa473fa954f2c85cba42605913');
define('ID_LANG', 1);
define('URL_CLIENT', 'http://shop24.e-stile.ru');

chdir(API_ROOT);

function __autoload($className)
{
    require_once(API_ROOT . "class/$className.php");
}

$headers = getallheaders();
if (!empty($headers['Token']))
    $token = trim($headers['Token']);

$dir_site = "shop24.e-stile.ru";
$dbConfigFIle = PATH_ROOT . $dir_site . '/public_html/system/config_db.php';
if (file_exists($dbConfigFIle))
    include $dbConfigFIle;

$url_path = str_replace("/api/" . API_VERSION, "", $_SERVER['REQUEST_URI']);
if ($url_path && $url_path != '/') {
    try {
        $url_path = parse_url($url_path, PHP_URL_PATH);
        $uri_parts = explode('/', trim($url_path, ' /'));
        $count = sizeof($uri_parts);
        $class = null;
        $method = 'fetch';
        for ($i = 0; $i < $count - 1; $i++) {
            $class .= ucfirst($uri_parts[$i]);
        }
        if ($class)
            $class = "Api" . $class;
        if ($count >= 2)
            $method = $uri_parts[$count - 1];
    } catch (Exception $e) {

    }
} else exit;

if ($method == "list")
    $method = "fetch";

$PROJECT_CONFIG = array("url" => $dir_site, "id_lang" => ID_LANG,
    "images_path" => PATH_ROOT . $dir_site .  "/public_html/wwwdata/www/images/rus/",
    "images_url" => "http://" . $dir_site .  "/wwwdata/www/images/rus/");

if (!empty($class) && class_exists($class)) {
    $api = new $class(file_get_contents('php://input'), $PROJECT_CONFIG, $CONFIG);
    if (method_exists($api, $method)) {
        $answer = $api->$method();
        if (!empty($answer))
            $api->outputData($answer);
        else $api->showErrorMessage('Не удалось обработать ваш запрос, возвращён пустой результат!');
    } else $api->showErrorMessage('Метод ' . $method . ' не найден!');
}

