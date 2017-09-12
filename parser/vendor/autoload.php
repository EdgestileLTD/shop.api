<?php

spl_autoload_register(function ($class) {
    $file = __DIR__ . DIRECTORY_SEPARATOR . str_replace('\\', '/', $class) . '.php';
    echo $file . "\n";
    if (file_exists($file)) {
        include_once $file;
    };
});