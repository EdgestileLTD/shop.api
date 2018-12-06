<?php

namespace SE\Shop;

use SE\DB;

// изображение
class Image extends Base
{
    private $dir;
    private $section;
    private $lang;

    // сборка
    function __construct($input = null)
    {
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');
        parent::__construct($input);

        $this->section = !empty($this->input["section"]) ? $this->input["section"] : $_GET['section'];
        $this->lang = $_SESSION['language'] ? $_SESSION['language'] : 'rus';
        $this->dir = DOCUMENT_ROOT . "/images";
        if ($this->section) {
            if ($this->section == "yandexphotos")
                $this->dir .= "/tmp";
            else {
                if ($this->section == "sections") {
                    $this->section = 'shopsections';
                }
                $this->dir .= "/$this->lang/{$this->section}";
            }
        }
        if (!file_exists($this->dir))
            mkdir($this->dir, 0700, true);
    }

    // получить
    public function fetch()
    {
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');
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
                    $item["name"] = $file;
                    $item["title"] = $file;
                    $item["weight"] = number_format(filesize($this->dir . "/" . $file), 0, '', ' ');
                    list($width, $height, $type, $attr) = getimagesize($this->dir . "/" . $file);
                    $item["sizeDisplay"] = $width . " x " . $height;
                    $item["imageUrl"] = !empty($this->section) ?
                        'http://' . HOSTNAME . "/images/rus/{$this->section}/" . $file :
                        'http://' . HOSTNAME . "/images/" . $file;
                    $item["imageUrlPreview"] = !empty($this->section) ?
                        "http://" . HOSTNAME . "/lib/image.php?size=64&img=images/rus/{$this->section}/" . $file :
                        "http://" . HOSTNAME . "/lib/image.php?size=64&img=images/" . $file;
                    $listFiles[] = $item;
                }
            }
            closedir($handleDir);
        }
        $this->result['count'] = $count;
        $this->result['items'] = $listFiles;
        return $listFiles;
    }

    // удалить
    public function delete()
    {
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');
        $files = $this->input["files"];

        $isUnused = (bool) $this->input["isUnused"];
        $usedImages = [];

        if ($this->section == 'shopprice' && $isUnused) {
            $u = new DB('shop_price', 'sp');
            $u->select('img name, note, text');
            $products = $u->getList();
            foreach ($products as $product) {
                if ($product['name'])
                    $usedImages[] = $product['name'];

                $media = [];
                if ($product["note"]) {
                    preg_match_all('/<img(?:\\s[^<>]*?)?\\bsrc\\s*=\\s*(?|"([^"]*)"|\'([^\']*)\'|([^<>\'"\\s]*))[^<>]*>/i', $product["note"], $media);
                }
                if ($product["text"]) {
                    preg_match_all('/<img(?:\\s[^<>]*?)?\\bsrc\\s*=\\s*(?|"([^"]*)"|\'([^\']*)\'|([^<>\'"\\s]*))[^<>]*>/i', $product["text"], $media);
                }
                if (!empty($media[1][0])) {
                    if (strpos($media[1][0], $this->hostname))
                        $media[1][0] = str_replace("http://" . $this->hostname, "", $media[1][0]);
                    if ((strpos($media[1][0], "http:") === false) && (strpos($media[1][0], "https:") === false)) {
                        $fileName = trim(str_replace("/images/{$this->lang}/{$this->section}", "", $media[1][0]), "/");
                        if (!in_array($fileName, $usedImages))
                            $usedImages[] = $fileName;
                    }
                }
            }

            $u = new DB('shop_img', 'si');
            $u->select('picture name');
            $images = $u->getList();
            foreach ($images as $image)
                if ($image['name'])
                    $usedImages[] = $image['name'];
        }
        if ($this->section == 'shopgroup' && $isUnused) {

            $u = new DB('shop_group', 'sg');
            $u->select('picture name, commentary, footertext');
            $groups = $u->getList();
            foreach ($groups as $group) {
                if ($group['name'])
                    $usedImages[] = $group['name'];

                $media = [];
                if ($group["commentary"]) {
                    preg_match_all('/<img(?:\\s[^<>]*?)?\\bsrc\\s*=\\s*(?|"([^"]*)"|\'([^\']*)\'|([^<>\'"\\s]*))[^<>]*>/i', $group["commentary"], $media);
                }
                if ($group["footertext"]) {
                    preg_match_all('/<img(?:\\s[^<>]*?)?\\bsrc\\s*=\\s*(?|"([^"]*)"|\'([^\']*)\'|([^<>\'"\\s]*))[^<>]*>/i', $group["footertext"], $media);
                }
                if (!empty($media[1][0])) {
                    if (strpos($media[1][0], $this->hostname))
                        $media[1][0] = str_replace("http://" . $this->hostname, "", $media[1][0]);
                    if ((strpos($media[1][0], "http:") === false) && (strpos($media[1][0], "https:") === false)) {
                        $fileName = trim(str_replace("/images/{$this->lang}/{$this->section}", "", $media[1][0]), "/");
                        if (!in_array($fileName, $usedImages))
                            $usedImages[] = $fileName;
                    }
                }

            }

            $u = new DB('shop_img', 'si');
            $u->select('picture name');
            $images = $u->getList();
            foreach ($images as $image)
                if ($image['name'])
                    $usedImages[] = $image['name'];
        }
        if ($this->section == 'newsimg' && $isUnused) {
            $u = new DB('news', 'n');
            $u->select('img name');
            $images = $u->getList();
            foreach ($images as $image)
                if ($image['name'])
                    $usedImages[] = $image['name'];
            $u = new DB('news_img', 'ni');
            $u->select('picture name');
            $images = $u->getList();
            foreach ($images as $image)
                if ($image['name'])
                    $usedImages[] = $image['name'];
        }
        if ($this->section== 'shopbrand' && $isUnused) {
            $u = new DB('shop_brand', 'sb');
            $u->select('image name, text, content');
            $brands = $u->getList();
            foreach ($brands as $brand) {
                if ($brand['name'])
                    $usedImages[] = $brand['name'];

                $media = [];
                if ($brand["text"]) {
                    preg_match_all('/<img(?:\\s[^<>]*?)?\\bsrc\\s*=\\s*(?|"([^"]*)"|\'([^\']*)\'|([^<>\'"\\s]*))[^<>]*>/i', $brand["text"], $media);
                }
                if ($brand["content"]) {
                    preg_match_all('/<img(?:\\s[^<>]*?)?\\bsrc\\s*=\\s*(?|"([^"]*)"|\'([^\']*)\'|([^<>\'"\\s]*))[^<>]*>/i', $brand["content"], $media);
                }
                if (!empty($media[1][0])) {
                    if (strpos($media[1][0], $this->hostname))
                        $media[1][0] = str_replace("http://" . $this->hostname, "", $media[1][0]);
                    if ((strpos($media[1][0], "http:") === false) && (strpos($media[1][0], "https:") === false)) {
                        $fileName = trim(str_replace("/images/{$this->lang}/{$this->section}", "", $media[1][0]), "/");
                        if (!in_array($fileName, $usedImages))
                            $usedImages[] = $fileName;
                    }
                }
            }
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
    public function post($tempFile = FALSE)
    {
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');
        $countFiles = count($_FILES);
        $ups = 0;
        $files = [];
        $items = [];

        for ($i = 0; $i < $countFiles; $i++) {
            $file = $_FILES["file$i"]['name'];
            $uploadFile = $this->dir . '/' . $file;
            while (file_exists($uploadFile)) {
                $ext = end(explode('.', $file));
                $fil = substr($file, 0, 0 - (strlen($ext) + 1));

                $num = end(explode('_', $fil));
                if ($num && is_numeric($num)) {
                    $fil = substr($fil, 0, 0 - strlen($num));
                    $num++;
                    $fil .= $num;
                } else {
                    $fil .= '_1';
                }
                $file = $fil . '.' . $ext;
                $uploadFile = $this->dir . '/' . $file;
            }

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
                    $item["title"] = $file;
                    $item["weight"] = number_format(filesize($uploadFile), 0, '', ' ');
                    list($width, $height, $type, $attr) = getimagesize($uploadFile);
                    $item["sizeDisplay"] = $width . " x " . $height;
                    $item["imageUrl"] = !empty($this->section) ?
                        'http://' . HOSTNAME . "/images/rus/{$this->section}/" . urlencode($file) :
                        'http://' . HOSTNAME . "/images/" . urlencode($file);
                    $item["imageUrlPreview"] = !empty($this->section) ?
                        "http://" . HOSTNAME . "/lib/image.php?size=64&img=images/rus/{$this->section}/" . urlencode($file) :
                        "http://" . HOSTNAME . "/lib/image.php?size=64&img=images/" . urlencode($file);
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

    // конвертировать имя
    private function convertName($name) {
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');
        $chars = array(" ", "#", ":", "!", "+", "?", "&", "@", "~", "%");
        return str_replace($chars, "_", $name);
    }

    // получить новое имя
    private function getNewName($dir, $name) {
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');
        $i = 0;
        $newName = $name = $this->convertName(trim($name));
        while (true) {
            if (!file_exists($dir . "/" . $newName))
                return $newName;
            $newName = substr($name, 0, strrpos($name, ".")) . "_" . ++$i . "." . end(explode(".", $name));
        }
    }

    // информация
    public function info($id = NULL)
    {
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');
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
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');
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
