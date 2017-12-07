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
            if ($_SESSION['isAuth'])
                $this->updateDB();
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
                header('Content-Type: application/json');
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


    public function getMySQLVersion()
    {
        $r = DB::query("select version()");
        $answer = $r->fetchAll();
        if ($answer) {
            $version = explode(".", $answer[0]);
            if (count($version) > 1) {
                return (int)$version[0] . $version[1];
            }
        }
        return 50;
    }

    public function correctFileUpdateForMySQL56($fileName)
    {
        file_put_contents($fileName, str_replace(" ON UPDATE CURRENT_TIMESTAMP", "", file_get_contents($fileName)));
    }

    public function updateDB()
    {
        $settings = new DB('se_settings', 'ss');
        $settings->select("db_version");
        $result = $settings->fetchOne();
        if (empty($result["dbVersion"]))
            DB::query("INSERT INTO se_settings (`version`, `db_version`) VALUE (1, 1)");
        if ($result["dbVersion"] < DB_VERSION) {
            $pathRoot =  $_SERVER['DOCUMENT_ROOT'] . '/api/update/sql/';
            DB::setErrorMode(\PDO::ERRMODE_SILENT);
            for ($i = $result["dbVersion"] + 1; $i <= DB_VERSION; $i++) {
                $fileUpdate = $pathRoot . $i . '.sql';
                if (file_exists($fileUpdate)) {
                    if ($this->getMySQLVersion() < 56)
                        $this->correctFileUpdateForMySQL56($fileUpdate);
                    $query = file_get_contents($fileUpdate);
                    try {
                        DB::query($query);
                        DB::query("UPDATE se_settings SET db_version=$i");
                    } catch (\PDOException $e) {
                        writeLog("Exception ERROR UPDATE {$i}.sql: ".$query);
                    }
                }
            }
            DB::setErrorMode(\PDO::ERRMODE_EXCEPTION);
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

    // отладка
    function debugging($group, $funct = null, $act = null)
    {    // группа_логов/функция/комент
        // значение:  True/False (печатать/не_печатать в логи)

        $print = array(
            'funct' => False,   // безымянные
        );

        if ($print[$group] == True) {
            $wrLog = __CLASS__;
            $Indentation = str_repeat(" ", (100 - strlen($wrLog)));
            $wrLog = "{$wrLog} {$Indentation}| Start function: {$funct}";
            $Indentation = str_repeat(" ", (150 - strlen($wrLog)));
            writeLog("{$wrLog}{$Indentation} | Act: {$act}");
        }
    }

}