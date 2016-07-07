<?php


abstract class ApiBase
{
    public $isRus = false;
    public $isCompress = false;
    public $dbh;

    protected $input;
    protected $projectConfig;
    protected $isError = false;
    protected $limit = 3;
    protected $imageSection;

    function __construct($input, $projectConfig, $dbConfig = null)
    {
        $this->input = json_decode($input, true);
        $this->projectConfig = $projectConfig;
        if ($dbConfig) {
            try {
                $this->dbh = new PDO("mysql:host=localhost;dbname={$dbConfig['DBName']}", $dbConfig['DBUserName'], $dbConfig['DBPassword']);
            } catch (PDOException $e) {

            }
        }
    }

    function __destruct()
    {
        $this->dbh = null;
    }

    private function normJsonStr($str)
    {
        $str = preg_replace_callback('/\\\u([a-f0-9]{4})/i', create_function('$m', 'return chr(hexdec($m[1])-1072+224);'), $str);
        return iconv('cp1251', 'utf-8', $str);
    }

    public function outputData($data)
    {
        if ($this->isError)
            header("HTTP/1.1 500 Internal Server Error");

        if (is_array($data)) {
            $data = json_encode($data);
            if ($this->isRus)
                $data = $this->normJsonStr($data);
        }
        if ($this->isCompress) {
            $prefix = "";
            if ($this->isCompress == 2) {
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

    public function showErrorMessage($errorMessage = null)
    {
        $this->isError = true;
        $this->outputData($errorMessage);
    }

    protected function pdoSet($allowed, &$values, $source = array())
    {
        $set = '';
        $values = array();
        foreach ($allowed as $field) {
            if (isset($source[$field])) {
                $set .= "`" . str_replace("`", "``", $field) . "`" . "=:$field, ";
                $values[$field] = $source[$field];
            }
        }
        return substr($set, 0, -2);
    }

    public function set()
    {
        $result = array();
        try {
            $this->dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->dbh->beginTransaction();

            $request = $this->input["request"];
            $newItems = $request["items"]["add"];
            $updateItems = $request["items"]["update"];
            $itemsResult = array_merge($this->insert($newItems), $this->update($updateItems));
            $result["response"]["items"] = $itemsResult;
            $result["response"]["server_time"] = time();

            $this->dbh->commit();
        } catch (Exception $e) {
            $this->dbh->rollBack();
            $this->isError = true;
            $result = 'Не удаётся сохранить отправленные данные!';
        }
        return $result;
    }


    protected function translit_str($str, $delimer = '_') {
        $translate = array(
            'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'ж' => 'g', 'з' => 'z',
            'и' => 'i', 'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n', 'о' => 'o', 'п' => 'p',
            'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u', 'ф' => 'f', 'ы' => 'i', 'э' => 'e', 'А' => 'A',
            'Б' => 'B', 'В' => 'V', 'Г' => 'G', 'Д' => 'D', 'Е' => 'E', 'Ж' => 'G', 'З' => 'Z', 'И' => 'I',
            'Й' => 'Y', 'К' => 'K', 'Л' => 'L', 'М' => 'M', 'Н' => 'N', 'О' => 'O', 'П' => 'P', 'Р' => 'R',
            'С' => 'S', 'Т' => 'T', 'У' => 'U', 'Ф' => 'F', 'Ы' => 'I', 'Э' => 'E', 'ё' => "yo", 'х' => "h",
            'ц' => "ts", 'ч' => "ch", 'ш' => "sh", 'щ' => "shch", 'ъ' => "", 'ь' => "", 'ю' => "yu", 'я' => "ya",
            'Ё' => "YO", 'Х' => "H", 'Ц' => "TS", 'Ч' => "CH", 'Ш' => "SH", 'Щ' => "SHCH", 'Ъ' => "", 'Ь' => "",
            'Ю' => "YU", 'Я' => "YA", '№' => ""
        );
        $str = trim($str);
        $string = strtr($str, $translate);

        return trim(preg_replace('/[^\w\d]+/i', $delimer, $string), $delimer);
    }


}