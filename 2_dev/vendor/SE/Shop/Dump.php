<?php

namespace SE\Shop;

use SE\MySQLDump as MySQLDump;
use SE\Exception as Exception;

class Dump extends Base
{
    public function info()
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
}