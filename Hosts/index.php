<?php
    //$file = fopen($_SERVER['DOCUMENT_ROOT']."/api/logsql.txt", "w");
    function writeLog($data) {
        $file = fopen($_SERVER['DOCUMENT_ROOT']."/api/logsql.txt", "a+");
        $query = "$data"."\n";
        fputs($file, $query);
        fclose($file);
    }

    date_default_timezone_set("Europe/Moscow");
    ini_set('display_errors',1);
    error_reporting(E_ALL);

    chdir($_SERVER['DOCUMENT_ROOT']);
    include '/admin/home/siteedit/config/config.php';
    require '/admin/home/siteedit/lib/function.php';
    require '/admin/home/siteedit/lib/lib_function.php';
    require '/admin/home/siteedit/lib/yaml/seYaml.class.php';
    require '/admin/home/siteedit/lib/se_db_mysql.php';
    include '/admin/home/siteedit/lib/seTable.class.php';
    require '/admin/home/siteedit/lib/api_host.class.php';

    define('ROOTSERVER_URI','http://siteedit24.ru');
    define('API_VERSION','1');
    define('API_STATUS','alpha');
    define('API_ROOT', $_SERVER['DOCUMENT_ROOT'].'/api/1/');
    define('API_URL', 'https://api.beget.ru/api/');
    define ('SE_INDEX_INCLUDED','');
    define('PATH_ROOT', '/home/e/edgestile/');

    $auth = explode('/', getcwd());
    $is_auth = (end($auth) == 'Auth');
    $isCompress = $_GET['compressed'];
    $isRus = $_GET['rus'];

    $json = json_decode(file_get_contents('php://input'));

    $json->sortBy = ($json->sortBy) ? $json->sortBy : 'id';
    $json->sortOrder = ($json->sortOrder) ? $json->sortOrder : 'asc';
    $json->offset = ($json->offset) ? $json->offset : 0;
    $json->limit = ($json->limit) ? $json->limit : 100;
    $json->language = ($json->language) ? $json->language : 'rus';
    $json->token = ($json->token) ? $json->token : $_GET['token'];
    if ($json->token) {
        $json->serial = substr($json->token, 0, 10);
        if (!empty($json->hostname)) {
            $ip_host = $_SERVER['REMOTE_ADDR'];
            if (substr($json->token, 10, 32) != md5($json->hostname.substr($json->token,0, 10).$ip_host)){
                echo $json->hostname.$ip_host;
                echo 'Not Authorize!';
                exit;
            }
        } else {
            $tokenDir = dirname(__FILE__) . '/../tokens';
            if (file_exists($tokenDir.'/'.$json->serial.'.dat')){
                $token = join('', file($tokenDir.'/'.$json->serial.'.dat'));
                list($token, $json->hostname) = explode('|', $token);
                if ($token != $json->token){
                    $json->hostname = '';
                    echo 'Not Authorize!';
                    exit;
                }
            }
        }
        $dbConfig = PATH_ROOT . $json->hostname . '/public_html/system/config_db.php';
        if (file_exists($dbConfig)){
            include $dbConfig;
            se_db_connect($CONFIG);
        } else {
            echo 'The database configuration is not found!';
            exit;
        }
    }

    function normJsonStr($str){
        $str = preg_replace_callback('/\\\u([a-f0-9]{4})/i', create_function('$m', 'return chr(hexdec($m[1])-1072+224);'), $str);
        return iconv('cp1251', 'utf-8', $str);
    }

    function outputData($data) {
        global $isCompress;
        global $isRus;
        $data = json_encode($data);
        if ($isRus)
            $data = normJsonStr($data);
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
            echo $prefix.gzcompress($data);
        }
        else echo $data;
    }

    function setField($isNew, &$table, $jsonField, $fieldName, $fieldType = 'string', $isIndex = false) {
        if (isset($jsonField)) {
            if (!$table->isFindField($fieldName)) {
               $table->addField($fieldName, $fieldType, $isIndex);
                //  log_write("ERROR QUERY: ".date('y-M-d h:m:s').':'.se_db_error()."\n\n");
            }
            if ($isNew)
                $table->{$fieldName} = $jsonField;
            else $table->addupdate($fieldName, "'$jsonField'");
            return true;
        }
        return false;
    }

    $uri = $_SERVER["REQUEST_URI"];
    $uri = $_SERVER["DOCUMENT_ROOT"].substr($uri, 0, strpos($uri,".api")).".php";

    if (file_exists($uri))
        require_once $uri;

    
