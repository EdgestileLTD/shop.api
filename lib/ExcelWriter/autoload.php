<?php

spl_autoload_register(function ($class) {
    if (strpos($class, "Ellumilel") === 0) {
        $class = trim(str_replace("Ellumilel", "", $class), "\\");
        $file = __DIR__ . DIRECTORY_SEPARATOR . str_replace('\\', '/', $class) . '.php';
        if (file_exists($file))
            include_once $file;
    }
});
