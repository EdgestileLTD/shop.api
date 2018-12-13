<?php

namespace SE\Shop;

use SE\MySQLDump as MySQLDump;
use SE\Exception as Exception;
use SE\DB as DB;

class Dump extends Base
{
    public function info($id = null)
    {
        $filePath = DOCUMENT_ROOT . "/files";
        if (!file_exists($filePath) || !is_dir($filePath))
            mkdir($filePath);
        $fileName = HOSTNAME . '.sql.gz';
        $filePath .= "/{$fileName}";
        $urlFile = 'http://' . HOSTNAME . "/files/{$fileName}";

        try {
            $dump = new MySQLDump();
            $dump->save($filePath);

            if (file_exists($filePath) && filesize($filePath)) {
                $this->result['url'] = $urlFile;
                $this->result['name'] = $fileName;
            } else throw new Exception();
        } catch (Exception $e) {
            $this->error = "Не удаётся создать дамп базы данных для вашего проекта!";
            throw new Exception($this->error);
        }
    }

    public function post($tempFile = FALSE)
    {
        $this->error = "Не удаётся развернуть дамп базы данных для вашего проекта!";
        try {
            $filePath = DOCUMENT_ROOT . "/files";
            if (!file_exists($filePath) || !is_dir($filePath))
                mkdir($filePath);
            $fileName = $_FILES["file"]['name'];
            $fileName = $filePath . "/" . $fileName;
            if (!move_uploaded_file($_FILES["file"]['tmp_name'], $fileName))
                exit;

            $query = null;

            /*$fp = gzopen($fileName, "r");
            while (!feof($fp)) {
                $ch = fread($fp, 1);
                $query .= $ch;
            }
            fclose($fp);*/

            $lines = gzfile($fileName);
            foreach ($lines as $line) {
                $query .= $line;
            }

            if ($query) {

                //file_put_contents($filePath . '/dump.sql', $query);

                //exec('mysql  -u' . DB::$connect['DBUserName'] . ' -p' . DB::$connect['DBPassword'] . ' ' . DB::$connect['DBName'] . ' < ' . $filePath . '/dump.sql');

                DB::exec($query);
            }

            $this->error = null;
        } catch (Exception $e) {
            throw new Exception($this->error);
        }
    }
}