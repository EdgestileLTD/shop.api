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
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');
        $this->input = empty($input) || is_array($input) ? $input : json_decode($input, true);
        $this->hostname = HOSTNAME;
    }

    public function initConnection($connection)
    {
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');
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
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');
        if (!empty($this->error) && $this->statusAnswer == 200)
            $this->statusAnswer = 500;
        switch ($this->statusAnswer) {
            case 200: {
                header('Content-Type: application/json');
                $this->result = $this->correctOutput($this->result);
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
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');
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
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');
        file_put_contents($fileName, str_replace(" ON UPDATE CURRENT_TIMESTAMP", "", file_get_contents($fileName)));
    }

    public function updateDB()
    {
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');
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
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');
        if (is_array($this->input))
            $this->input[$name] = $value;
    }

    function __get($name)
    {
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');
        if (is_array($this->input) && isset($this->input[$name]))
            return $this->input[$name];
    }

    // отладка
    protected function debugging($group, $funct=null, $class=null, $act=null, $array=null)
    {
        /**
         * $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');
         *
         * $this->debugging('shop/base dataCurrencies', __FUNCTION__.' '.__LINE__, __CLASS__,
         *     'start', $array=array("this->result"=>$this->result));
         */

        if (API_STATUS == "dev") {

            // [True | Print Log]  [False]
            $print = array(
                'funct'            =>false,   // общие
                'special'          =>false,   // значения в модулях

                'shop/base'        =>false,
                'order'            =>false,
            );

            if ($print[$group] == True) {

                $thi = get_class($this);
                $thisPrint  = "ThCl: {$thi}";
                $groupPrint = "Gr: {$group}";
                $classPrint = "Cl: {$class}";
                $functPrint = "F: {$funct}";
                $actPrint   = "Act: {$act}";

                $ind = 25;      $sy = " ";      $col = " | ";
                $groupLen   = $groupPrint.str_repeat($sy,(15   - mb_strlen($groupPrint))).$col;
                $thisLen    = $thisPrint.str_repeat( $sy,($ind - mb_strlen($thisPrint)) ).$col;
                $classLen   = $classPrint.str_repeat($sy,($ind - mb_strlen($classPrint))).$col;
                $functLen   = $functPrint.str_repeat($sy,($ind - mb_strlen($functPrint))).$col;
                $actLen     = $actPrint.str_repeat(  $sy,($ind - mb_strlen($actPrint))  ).$col;

                writeLog($groupLen.$actLen.$functLen.$classLen.$thisLen);
                if ($array!=null) writeLog($array, false);
            }
        }
    }

    private function correctOutput($output)
    {
        $output["text"] = str_replace('src="/images', 'src="' . $this->protocol . "://" . $this->hostname . '/images', $output["text"]);
        $output["note"] = str_replace('src="/images', 'src="' . $this->protocol . "://" . $this->hostname . '/images', $output["note"]);
        $output["description"] = str_replace('src="/images', 'src="' . $this->protocol . "://" . $this->hostname . '/images', $output["description"]);

        return $output;
    }


}