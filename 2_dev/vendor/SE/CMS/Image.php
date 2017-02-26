<?php

namespace SE\CMS;


class Image extends Base
{
    private $dir;
    private $section;
    private $lang;

    function __construct($input = null)
    {
        parent::__construct($input);

        $this->section = !empty($this->input["section"]) ? $this->input["section"] : $_GET['section'];
        $this->lang = $_SESSION['language'] ? $_SESSION['language'] : 'rus';
        $this->dir = DOCUMENT_ROOT . (($this->seFolder) ? '/' . $this->seFolder : '') . "/images";
        if ($this->section) {
            if ($this->section == "yandexphotos")
                $this->dir .= "/tmp";
            else $this->dir .= ($this->section) ? "/{$this->section}" : "";
        }
        writeLog($this->dir);
        if (!file_exists($this->dir))
            mkdir($this->dir, 0700, true);
    }

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
                $ext = strtolower(end(explode('.', $file)));
                if ($file == '.' || $file == '..' || !in_array($ext, array('jpg', 'jpeg', 'gif', 'png', 'svg')))
                    continue;
                if ($searchStr && (strpos(mb_strtolower($file), $searchStr) === false))
                    continue;
                $count++;
                if ($i++ < $this->offset)
                    continue;

                if ($count <= $this->limit + $this->offset) {
                    $item = [];
                    $item["name"] = $file;
                    $item["title"] = $file;
                    $item["weight"] = number_format(filesize($this->dir . "/" . $file), 0, '', ' ');
                    list($width, $height, $type, $attr) = getimagesize($this->dir . "/" . $file);
                    $item["sizeDisplay"] = $width . " x " . $height;
                    $item["imageUrl"] = !empty($this->section) ?
                        'http://' . HOSTNAME . (($this->seFolder) ? '/' . $this->seFolder : '') . "/images/{$this->section}/" . $file :
                        'http://' . HOSTNAME . (($this->seFolder) ? '/' . $this->seFolder : '') . "/images/" . $file;
                    $item["imageUrlPreview"] = !empty($this->section) ?
                        "http://" . HOSTNAME . "/lib/image.php?size=64&img=" . (($this->seFolder) ? $this->seFolder . '/' : '') . "images/{$this->section}/" . $file :
                        "http://" . HOSTNAME . "/lib/image.php?size=64&img=" . (($this->seFolder) ? $this->seFolder . '/' : '') . "images/" . $file;
                    $listFiles[] = $item;
                }
            }
            closedir($handleDir);
        }
        $this->result['count'] = $count;
        $this->result['items'] = $listFiles;
        return $listFiles;
    }

    public function delete()
    {
        $files = $this->input["files"];

        $isUnused = (bool)$this->input["isUnused"];
        $usedImages = [];


        if ($isUnused && false) { // Пока отключено
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

    }

    public function post()
    {
        $countFiles = count($_FILES);
        $ups = 0;
        $files = [];
        $items = [];

        for ($i = 0; $i < $countFiles; $i++) {
            $file = $_FILES["file$i"]['name'];
            $ext = end(explode('.', $file));
            $title = trim(str_replace('.'.$ext, '', $_FILES["file$i"]['name']));
            $file = strtolower(se_translite_url($title)).'.'.$ext;

            $uploadFile = $this->dir . '/' . $file;
            $fileTemp = $_FILES["file$i"]['tmp_name'];
            if (!getimagesize($fileTemp)) {
                $this->error = "Ошибка! Найден файл не являющийся изображением!";
                return;
            }
            if (!filesize($fileTemp) || move_uploaded_file($fileTemp, $uploadFile)) {
                if (file_exists($uploadFile)) {
                    $files[] = $uploadFile;
                    $item = [];
                    $item["name"] = $file;
                    $item["title"] = $title;
                    $item["weight"] = number_format(filesize($uploadFile), 0, '', ' ');
                    list($width, $height, $type, $attr) = getimagesize($uploadFile);
                    $item["sizeDisplay"] = $width . " x " . $height;
                    $item["imageUrl"] = !empty($this->section) ?
                        'http://' . HOSTNAME . (($this->seFolder) ? '/' . $this->seFolder : '') . "/images/{$this->section}/" . $file :
                        'http://' . HOSTNAME . (($this->seFolder) ? '/' . $this->seFolder : '') . "/images/" . $file;
                    $item["imageUrlPreview"] = !empty($this->section) ?
                        "http://" . HOSTNAME . "/lib/image.php?size=64&img=" . (($this->seFolder) ? $this->seFolder . '/' : '') . "images/{$this->section}/" . $file :
                        "http://" . HOSTNAME . "/lib/image.php?size=64&img=" . (($this->seFolder) ? $this->seFolder . '/' : '') . "images/" . $file;
                    $items[] = $item;
                }
                $ups++;
            }
        }
        if ($ups == $countFiles)
            $this->result['items'] = $items;
        else $this->error = "Не удается загрузить файлы!";

        return $items;
    }

    private function convertName($name)
    {
        $chars = array(" ", "#", ":", "!", "+", "?", "&", "@", "~", "%");
        return str_replace($chars, "_", $name);
    }

    private function getNewName($dir, $name)
    {
        $i = 0;
        $newName = $name = $this->convertName(trim($name));
        while (true) {
            if (!file_exists($dir . "/" . $newName))
                return $newName;
            $newName = substr($name, 0, strrpos($name, ".")) . "_" . ++$i . "." . end(explode(".", $name));
        }
    }

    public function info()
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
