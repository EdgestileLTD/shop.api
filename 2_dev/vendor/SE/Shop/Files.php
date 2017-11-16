<?php

namespace SE\Shop;

use SE\DB;

// файлы
class Files extends Base
{
    private $dir;
    private $section;
    private $lang;

    // сборка
    function __construct($input = null)
    {
        parent::__construct($input);

        $this->section = !empty($this->input["section"]) ? $this->input["section"] : $_GET['section'];
        $this->dir = DOCUMENT_ROOT . "/files";
        if (!file_exists($this->dir))
            mkdir($this->dir, 0700, true);
    }

    // получить
    public function fetch()
    {
        if (function_exists("mb_strtolower"))
            $searchStr = mb_strtolower(trim($this->search));
        else $searchStr = strtolower(trim($this->search));
        if ($searchStr)
            $this->offset = 0;
        $listFiles = [];
        $count = 0;
        if (file_exists($this->dir) && is_dir($this->dir)) {
            $handleDir = opendir($this->dir);
            $i = 0;
            while (($file = readdir($handleDir)) !== false) {
                if ($file == '.' || $file == '..')
                    continue;
                if ($searchStr && (strpos(mb_strtolower($file), $searchStr) === false))
                    continue;
                $count++;
                if ($i++ < $this->offset)
                    continue;

                if ($count <= $this->limit + $this->offset) {
                    $item = [];
                    $item["name"] = 'Скачать '.$file;
                    $item["file"] = $file;
                    $listFiles[] = $item;
                }
            }
            closedir($handleDir);
        }
        $this->result['count'] = $count;
        $this->result['items'] = $listFiles;
        return $listFiles;
    }

    // удалить файлы
    public function delete()
    {
        return FALSE;
        $files = $this->input["files"];

        $isUnused = (bool) $this->input["isUnused"];
        $usedFiles = array();

        if ($this->section == 'shopprice' && $isUnused) {
            $u = new DB('shop_files', 'si');
            $u->select('file name');
            $files = $u->getList();
            foreach ($files as $file)
                if ($file['name'])
                    $usedFiles[] = $file['name'];
        }

        if (!empty($this->section)) {
            if ($isUnused) {
                $handleDir = opendir($this->dir);
                while (($file = readdir($handleDir)) !== false) {
                    if ($file == '.' || $file == '..')
                        continue;
                    if (!in_array($file, $usedImages))
                        unlink($this->dir . "/" . $file);
                }
            } else
                foreach ($files as $file)
                    if (!empty($file))
                        unlink($this->dir . "/" . $file);
        } else $this->error = "Не удаётся удалить файлы изображений!";
    }

    // после
    public function post()
    {
        $countFiles = count($_FILES);
        $ups = 0;
        $files = [];
        $items = [];

        for ($i = 0; $i < $countFiles; $i++) {
            $file = $this->convertName($_FILES["file$i"]['name']);
            $uploadFile = $this->dir . '/' . $file;
            $fileTemp = $_FILES["file$i"]['tmp_name'];

            if (!filesize($fileTemp) || move_uploaded_file($fileTemp, $uploadFile)) {
                if (file_exists($uploadFile)) {
                    $files[] = $uploadFile;
                    $item = array();
                    $item["name"] = $file;
                    $item["file"] = $file;
                    $item["ext"] = strtoupper(substr(strrchr($file,'.'), 1));
                    $item['url'] = 'http://' . HOSTNAME . "/files/" . $item["file"];
                    $items[] = $item;
                    //writeLog($item);
                }
                $ups++;
            }

        }
        if ($ups == $countFiles)
            $this->result['items'] = $items;
        else $this->error = "Не удается загрузить файлы!";

        return $items;
    }

    // конвертировать имя
    private function convertName($name) {
        $chars = array(" ", "#", ":", "!", "+", "?", "&", "@", "~", "%");
        return str_replace($chars, "_", $name);
    }

    // получить имя
    private function getNewName($dir, $name) {
        $i = 0;
        $newName = $name = $this->convertName(trim($name));
        while (true) {
            if (!file_exists($dir . "/" . $newName))
                return $newName;
            $newName = substr($name, 0, strrpos($name, ".")) . "_" . ++$i . "." . end(explode(".", $name));
        }
    }

    // информация
    public function info($id = null)
    {
        $names = $this->input["listValues"];
        $newNames = [];
        foreach ($names as $name)
            $newNames[] = $this->getNewName($this->dir, $name);
        $item['newNames'] = $newNames;
        $this->result['count'] = 1;
        $this->result['items'][0] = $item;
        return $item;
    }

    // проверить имена
    public function checkNames()
    {
        $items = [];
        $names = $this->input["names"];
        foreach ($names as $name) {
            $item = [];
            $item['oldName'] = $name;
            $item['newName'] = $this->getNewName($this->dir, $name);
            $items[] = $item;
        }
        $this->result['items'] = $items;
    }


}
