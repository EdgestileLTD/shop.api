<?php

    abstract class ApiBase {
        public $isRus = false;
        public $isCompress = false;
        protected static $dbh;

        protected $input;
        protected $projectConfig;
        protected $isError = false;
        protected $limit = 3;
        protected $imageSection;

        public function __construct($input, $projectConfig, $dbConfig = null) {
            $this->input = json_decode($input, true);
            $this->projectConfig = $projectConfig;
            if($dbConfig) {
                try {
                    self::$dbh = database::getInstance();
                } catch(Exception $e) {
                    $this->isError = true;
                }
            }
        }

        private function normJsonStr($str) {
            $str = preg_replace_callback('/\\\u([a-f0-9]{4})/i', create_function('$m', 'return chr(hexdec($m[1])-1072+224);'), $str);

            return iconv('cp1251', 'utf-8', $str);
        }

        public function generateData($data, $err = false, $msg = '') {
            if($err) {
                $this->isError = true;
                $output['status'] = 'error';
                $output['text'] = $data;
                $output['text_error'] = $msg;
            } else {
                $output['status'] = 'ok';
                $output['data'] = $data;
            }
            $output['server_time'] = time();

            return $output;

        }
        public function outputData($data) {
            if($this->isError) {
                header("HTTP/1.1 500 Internal Server Error");
                echo json_encode($data);
                return;
            }
            if(is_array($data)) {
                $data = json_encode($data);
                if($this->isRus)
                    $data = $this->normJsonStr($data);
            }
            if($this->isCompress) {
                $prefix = "";
                if($this->isCompress == 2) {
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

        public function showErrorMessage($errorMessage = null) {
            $this->outputData($errorMessage);
        }
//
//        protected function pdoSet($allowed, &$values, $source = array()) {
//            $set = $values = array();
//            foreach($allowed as $field) {
//                if(isset($source[$field])) {
//                    $set[] = $field . "`=:$field";
//                    $values[$field] = $source[$field];
//                }
//            }
//
//            return (!empty($set)) ? '`' . implode(", `", $set) : '';
//        }

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