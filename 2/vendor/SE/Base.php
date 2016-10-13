<?php

namespace SE;

class Base
{
    protected $result;
    protected $input;
    protected $error;
    protected $isTableMode = false;
    protected $statusAnswer = 200;
    protected $protocol = 'http';
    protected $hostname;
    protected $urlImages;
    protected $dirImages;
    protected $imageSize = 256;
    protected $imagePreviewSize = 64;

    function __construct($input = null)
    {
        $this->input = empty($input) || is_array($input) ? $input : json_decode($input, true);
        $this->hostname = HOSTNAME;
    }

    public function initConnection($connection)
    {
        try {
            DB::initConnection($connection);
            return true;
        } catch (Exception $e) {
            $this->error = 'Не удаётся подключиться к базе данных!';
            return false;
        }
    }

    public function output()
    {
        if (!empty($this->error) && $this->statusAnswer == 200)
            $this->statusAnswer = 500;
        switch ($this->statusAnswer) {
            case 200: {
                echo json_encode($this->result);
                exit;
            }
            case 404: {
                header("HTTP/1.1 404 Not found");
                echo $this->error;
                exit;
            }
            case 500: {
                header("HTTP/1.1 500 Internal Server Error");
                echo $this->error;
                exit;
            }
        }
    }

    function __set($name, $value)
    {
        if (is_array($this->input))
            $this->input[$name] = $value;
    }

    function __get($name)
    {
        if (is_array($this->input) && isset($this->input[$name]))
            return $this->input[$name];
    }


}