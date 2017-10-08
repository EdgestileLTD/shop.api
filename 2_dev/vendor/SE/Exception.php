<?php

namespace SE;
// класс ИСКЛЮЧЕНИЕ
class Exception extends \PDOException
{
    // собрать
    public function __construct($message = "", $code = 0, Exception $previous = null) {
        parent::__construct($message, $code, $previous);
        writeLog("Exception: " . $message);
    }
}