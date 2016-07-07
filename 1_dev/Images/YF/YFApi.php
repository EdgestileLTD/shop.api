<?php

class YFApi
{
    private $uriApi = 'http://api-fotki.yandex.ru';
    private $login;
    private $token;
    private $previewSizeYandex = "XXS";

    public function __construct($login, $token)
    {
        $this->login = $login;
        $this->token = $token;
    }

    public function createAlbum($title, $summary)
    {
        $idAlbum = null;
        $body = '<entry xmlns="http://www.w3.org/2005/Atom" xmlns:f="yandex:fotki">';
        $body .= "<title>{$title}</title><summary>{$summary}</summary></entry>";
        $data = $this->request("/api/users/{$this->login}/albums/", "application/atom+xml; charset=utf-8; type=entry", $body);
        $entry = simplexml_load_string($data);
        $details = $entry->children('http://www.w3.org/2005/Atom');
        $idAlbum = end(explode(":", (string)$details->id));

        return $idAlbum;
    }

    public function getAlbums()
    {
        $albums = array();
        $data = $this->request("/api/users/{$this->login}/albums/");

        $feed = simplexml_load_string($data);
        $entries = $feed->children('http://www.w3.org/2005/Atom')->entry;
        foreach ($entries as $entry)
            $albums[] = array("id" => end(explode(":", (string)$entry->id)), "title" => (string)$entry->title);

        return $albums;
    }

    public function getPhotos($idAlbum, $limit, $offset)
    {
        $url = "/api/users/{$this->login}/album/{$idAlbum}/photos/";
        $url .= "?limit={$limit}";

        $data = $this->request($url);

        $feed = simplexml_load_string($data);
        $params = $feed->xpath('//f:image-count');
        foreach ($params as $param) {
            $count = (int)$param->attributes()->value;
            break;
        }
        $entries = $feed->children('http://www.w3.org/2005/Atom')->entry;
        foreach ($entries as $entry) {
            $details = $entry->children('http://www.w3.org/2005/Atom');
            $link = $details->link[2]->attributes()->href;
            $previewSrc = $details->content->attributes()->src;
            $preview = preg_replace('/(.*)((_|-)+)(\w{1,4})$/', '$1$2' . $this->previewSizeYandex, $previewSrc);

            $sizeDisplay = "75 x 75";
            $weight = 0;

            $params = $entry->xpath('f:img');
            foreach ($params as $param) {
                if ($param->attributes()->size == "orig") {
                    $sizeDisplay = $param->attributes()->width . " x " . $param->attributes()->height;
                    $weight = number_format((int)$param->attributes()->bytesize, 0, '', ' ');
                    break;
                }
            }

            $listFiles[] = array(
                'id' => (string)$details->id,
                'title' => (string)$details->title,
                'name' => (string)$previewSrc,
                'imageUrl' => (string)$previewSrc,
                'imageUrlPreview' => (string)$preview,
                'sizeDisplay' => $sizeDisplay,
                'weight' => $weight,
                'link' => (string)$link
            );
        };

        $photos = array("count" => $count, "list" => $listFiles);

        return $photos;
    }

    private function getMultiPartData($file, $boundary)
    {
        $binary = file_get_contents($file);
        $fileName = basename($file);

        $body = "--" . $boundary . "\r\n";
        $body .= 'Content-Disposition: form-data;  name="image"; filename="' . $fileName . '"' . "\r\n";
        $body .= 'Content-Type: image/jpeg' . "\r\n\r\n";
        $body .= $binary;
        $body .= "\r\n";
        $body .= "--" . $boundary . "\r\n";
        $body .= 'Content-Disposition: form-data;  name="title"' . "\r\n\r\n";
        $body .= $fileName;

        return $body;
    }

    public function uploadPhoto($idAlbum, $file)
    {
        $boundary = md5(mt_rand() . microtime());
        $data = $this->getMultiPartData($file, $boundary);
        $data = $this->request("/api/users/{$this->login}/album/{$idAlbum}/photos/",
            "multipart/form-data;  boundary={$boundary}", $data);
        $entry = simplexml_load_string($data);
        $link = $entry->link[2]->attributes()->href;
        $previewSrc = $entry->content->attributes()->src;
        $preview = preg_replace('/(.*)((_|-)+)(\w{1,4})$/', '$1$2' . $this->previewSizeYandex, $previewSrc);

        $sizeDisplay = "75 x 75";
        $weight = 0;

        $params = $entry->xpath('f:img');
        foreach ($params as $param) {
            if ($param->attributes()->size == "orig") {
                $sizeDisplay = $param->attributes()->width . " x " . $param->attributes()->height;
                $weight = number_format((int)$param->attributes()->bytesize, 0, '', ' ');
                break;
            }
        }

        return array(
            'id' => (string)$entry->id,
            'title' => (string)$entry->title,
            'name' => (string)$previewSrc,
            'imageUrl' => (string)$previewSrc,
            'imageUrlPreview' => (string)$preview,
            'sizeDisplay' => $sizeDisplay,
            'weight' => $weight,
            'link' => (string)$link
        );
    }

    function request($uri, $contentType = null, $body = null)
    {
        $result = null;
        $host = "api-fotki.yandex.ru";
        $out = (empty($body) ? "GET" : "POST") . " {$uri} HTTP/1.1\n";
        $out .= "Host: {$host}\n";
        $out .= 'Authorization: OAuth ' . $_SESSION['tokenYandex'] . "\n";
        if ($contentType)
            $out .= "Content-Type: {$contentType}\n";
        if ($body)
            $out .= "Content-Length: " . strlen($body) . "\n";
        $out .= "\n";
        if ($body) {
            $out .= $body;
        }
        $data = null;
        $fp = fsockopen("{$host}", 80, $errno, $errstr, 30);
        fputs($fp, $out);
        while ($gets = fgets($fp))
            $data .= $gets;
        fclose($fp);
        
        $data = substr($data, $p = strpos($data, "\r\n\r\n") + 4, strlen($data) - $p);
        $data = explode("\r\n", $data);
        $i = 0;
        foreach ($data as $ln)
            if($i++ & 1)
                $result .= $ln;

        return trim($result);
    }

}