<?php

error_reporting(E_ALL);
// ошибка журнала
function log_error( $num, $str, $file, $line, $context = null )
{
    if($num > 8){
        $a = explode('api',$file);
        $file = array_pop($a);
        writeLog($num.'['.$file.'|'.$line.'] '.$str, 'ERROR');
    }
}

set_error_handler('log_error');


spl_autoload_register(function ($class) {
    $file = __DIR__ . DIRECTORY_SEPARATOR . str_replace('\\', '/', $class) . '.php';
    if (file_exists($file))
        include_once $file;
    //else {
    //    writeLog('NOT THIS CLASS '.$class);
    //}
});
