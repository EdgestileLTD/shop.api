<?php

//доверенные сервера
$allowableServers = array("5.101.153.108", "5.101.153.40");

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

$headers = getallheaders();
if (!empty($headers['Secookie']))
    session_id($headers['Secookie']);
session_start();

function writeLog($data)
{
    if (!is_string($data))
        $data = print_r($data, 1);

    $file = fopen($_SERVER['DOCUMENT_ROOT'] . "/api/debug.log", "a+");
    $query = "$data" . "\n";
    fputs($file, $query);
    fclose($file);
}

chdir($_SERVER['DOCUMENT_ROOT']);

date_default_timezone_set("Europe/Moscow");
ini_set('display_errors', 0);
error_reporting(E_ALL);

define('IS_EXT', file_exists($_SERVER['DOCUMENT_ROOT'] . '/system/main/init.php'));

define('ROOTSERVER_URI', 'http://siteedit24.ru');
define('API_ROOT', $_SERVER['DOCUMENT_ROOT'] . '/api/4/');
define('URL_API_ORIGINAL', 'http://upload.beget.edgestile.net/api');
define('SE_INDEX_INCLUDED', '');
define('API_ROOT_URL', "http://" . $_SERVER['SERVER_NAME'] . "/api/4");
define('MODULE_SE_SECTION', 'ashop_section');

$IS_OUTPUT_DATA = true;

if (IS_EXT) {
    require_once 'system/main/init.php';
    require_once 'api/update.php';
    define('PATH_ROOT', getcwd());
} else {
    require_once '/home/e/edgestile/admin/home/siteedit/config/config.php';
    require_once '/home/e/edgestile/admin/home/siteedit/lib/function.php';
    require_once '/home/e/edgestile/admin/home/siteedit/lib/lib_function.php';
    require_once '/home/e/edgestile/admin/home/siteedit/lib/yaml/seYaml.class.php';
    require_once '/home/e/edgestile/admin/home/siteedit/lib/se_db_mysql.php';
    require_once '/home/e/edgestile/admin/home/siteedit/lib/seTable.class.php';
    require_once '/home/e/edgestile/admin/home/siteedit/lib/api_host.class.php';
    define('PATH_ROOT', '/home/e/edgestile/');
}

define('LP_ROOT', PATH_ROOT . "se-24.com/");
define('LP_ROOT_PROJECTS', LP_ROOT . "projects/");

$uri = $_SERVER["REQUEST_URI"];
$path = parse_url($uri);
$path = explode("/", $path['path']);
array_pop($path);
$apiObject = end($path);
$auth = explode('/', getcwd());
$is_auth = (end($auth) == 'Auth');
$isCompress = $_GET['compressed'];
$php_input = file_get_contents('php://input');
$json = json_decode($php_input);
$isLp = $headers["Lp"] == "true";

if ($headers['Origin']) {
    $url = parse_url($headers['Origin']);
    if ($url) {
        if ($url['host'] == 'shop.siteedit24.com') {
            header("Access-Control-Allow-Origin: http://shop.siteedit24.com");
            header("Access-Control-Allow-Credentials: true");
            header("Access-Control-Allow-Headers: Token, Secookie");
        }

        if ($url['host'] == 'localhost' && $url['port'] == 1337) {
            header("Access-Control-Allow-Origin: http://localhost:1337");
            header("Access-Control-Allow-Credentials: true");
            header("Access-Control-Allow-Headers: Token, Secookie");
        }
    }
}

if (empty($_SESSION['token'])) {
    if (!empty($headers['Token']))
        $json->token = $headers['Token'];
    else $json->token = ($json->token) ? $json->token : $_GET['token'];
    $_SESSION['token'] = $json->token;
} else $json->token = $_SESSION['token'];
if (!empty($headers['Login']))
    $authLogin = $headers['Login'];
if (!empty($headers['Password']))
    $authPassword = $headers['Password'];
if (empty($json->hostname))
    $json->hostname = !empty($_GET['hostname']) ? $_GET['hostname'] : null;

$json->sortBy = !empty($json->sortBy) ? $json->sortBy : 'id';
$json->sortOrder = !empty($json->sortOrder) ? $json->sortOrder : 'desc';
$json->offset = !empty($json->offset) ? $json->offset : 0;
$json->limit = !empty($json->limit) ? $json->limit : 100;
$DBH = null;

if ($json->token) {
    list($json->hostname, $json->token) = explode('||', cryptDecodeStr($json->token));
    if (IS_EXT)
        $dbConfig = 'system/config_db.php';
    else {
        if ($isLp) {
            $lpPath = explode(".", $json->hostname);
            if ($lpPath) {
                $codeLp = $lpPath[0];
                $lpPath = LP_ROOT_PROJECTS . $codeLp;
                $dbConfig = $lpPath . '/config/config_db.php';
                if (!file_exists($dbConfig))
                    include $_SERVER['DOCUMENT_ROOT'] . "/api/tools/lp/AddDB.php";
            }
        } else $dbConfig = PATH_ROOT . $json->hostname . '/public_html/system/config_db.php';
    }
    if (file_exists($dbConfig)) {
        include $dbConfig;
        se_db_connect($CONFIG);
        if (class_exists("PDO"))
            $DBH = new PDO("mysql:host={$CONFIG['HostName']};dbname={$CONFIG['DBName']}", $CONFIG['DBUserName'], $CONFIG['DBPassword']);
    } else {
        echo 'The database configuration is not found!';
        exit;
    }
    if (IS_EXT)
        $ver_file = 'lib/version';
    else $ver_file = PATH_ROOT . $json->hostname . '/public_html/lib/version';
    if (file_exists($ver_file)) {
        $core_version = trim(file_get_contents($ver_file));
        $core_version = explode(':', $core_version);
        $core_version = ($core_version[1]);
        $core_version = (!empty($core_version)) ? $core_version : "5.1";
    }
    define('CORE_VERSION', $core_version);
} else {
    echo "Not authorize";
    exit;
}

function outputData($data)
{
    global $isCompress;
    global $IS_OUTPUT_DATA;

    if (!$IS_OUTPUT_DATA)
        return;

    if (is_array($data))
        $data = json_encode($data);
    if ($isCompress) {
        $prefix = "";
        if ($isCompress == 2) {
            $prefix = "0000";
            $size = strlen($data);
            $prefix[0] = chr($size >> 24);
            $prefix[1] = chr($size >> 16);
            $prefix[2] = chr($size >> 8);
            $prefix[3] = chr($size);
        }
        echo $prefix . gzcompress($data);
    } else echo $data;
}

function setField($isNew, &$table, $jsonField, $fieldName, $fieldType = 'string', $isIndex = false)
{
    if (isset($jsonField)) {
        if (!$table->isFindField($fieldName))
            $table->addField($fieldName, $fieldType, $isIndex);
        if ($isNew) {
            if (is_bool($jsonField) || is_int($jsonField) || is_double($jsonField) || !empty($jsonField))
                $table->{$fieldName} = $jsonField;
        } else {
            if (is_bool($jsonField) || is_int($jsonField) || is_double($jsonField))
                $table->addupdate($fieldName, "'$jsonField'");
            else {
                if (!empty($jsonField))
                    $table->addupdate($fieldName, "'" . se_db_input($jsonField) . "'");
                else $table->addupdate($fieldName, "null");
            }
        }
        return true;
    }
    return false;
}

if ($apiObject != "Auth" && empty($_SESSION['isAuth']) && !in_array($_SERVER["REMOTE_ADDR"], $allowableServers)) {
    $status['status'] = 'error';
    $status['errortext'] = 'Необходима авторизация!';
    outputData($status);
    exit;
}

$uri = $_SERVER["DOCUMENT_ROOT"] . substr($uri, 0, strpos($uri, ".api")) . ".php";

setlocale(LC_NUMERIC, 'C');
if (file_exists($uri))
    require_once $uri;