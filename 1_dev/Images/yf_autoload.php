<?php

function __dm_autoload_fotki($class_name)
{
    if (file_exists($file = dirname(__FILE__) . "/YF/" . $class_name . ".php"))
        require_once $file;
}

spl_autoload_register('__dm_autoload_fotki');
