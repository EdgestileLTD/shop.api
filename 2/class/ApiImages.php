<?php

class ApiImages extends ApiBase
{

    public function upload()
    {
        $section = 'product';
        $images_dir = $this->projectConfig["images_path"] . $section;

        if (!file_exists($images_dir)) {
            $dirs = explode('/', $images_dir);
            $path = null;
            foreach ($dirs as $d) {
                $path .= $d;
                if (!file_exists($path))
                    mkdir($path, 0700);
                $path .= '/';
            }
        }

        $countFiles = count($_FILES);
        $ups = 0;
        for ($i = 0; $i < $countFiles; $i++) {
            $uploadFile = $images_dir . '/' . basename($_FILES["file$i"]['name']);
            $fileTemp = $_FILES["file$i"]['tmp_name'];
            if (!getimagesize($fileTemp))
                return "Ошибка! Найден файл не являющийся изображением!";
            if (!filesize($fileTemp) || move_uploaded_file($fileTemp, $uploadFile))
                $ups++;
        }

        if ($ups == $countFiles)
            return array("status" => "ok");
        return "Не удается загрузить файлы!";
    }


    protected function insert($items)
    {
        // TODO: Implement insert() method.
    }

    protected function update($items)
    {
        // TODO: Implement update() method.
    }
}