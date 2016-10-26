<?php

namespace SE\CMS;

class Pages extends Base
{
    private $pathPages;
    private $pathEdit;
    private $fileSource;
    private $fileEdit;

    function __construct($input) {
        parent::__construct($input);
        $this->pathPages = $this->projectFolder . "/pages/";
        $this->pathEdit = $this->projectFolder . "/edit/";
        if (!is_dir($this->pathEdit . "pages/"))
            mkdir($this->pathEdit . "pages/", 0700, 1);
        if (!empty($this->input["name"])) {
            $this->fileSource = $this->pathPages . "{$this->input["name"]}.xml";
            $this->fileEdit = $this->pathEdit . "pages/{$this->input["name"]}.xml";
            if (!file_exists($this->fileEdit))
                copy($this->fileSource, $this->fileEdit);
        }
    }

    public function fetch()
    {
        $fileSource = $this->projectFolder . "/pages.xml";
        $filePages = $this->projectFolder . "/edit/pages.xml";
        if (!file_exists($filePages))
            copy($fileSource, $filePages);
        $pages = simplexml_load_file($filePages);
        if (!empty($this->input["searchText"])){
            $items = $this->getPatches($pages);
        } else {
            $items = $this->getTree($pages);
        }
        $this->result = array('items'=>$items, 'count'=>count($items));
    }

    public function info()
    {
        $this->result = simplexml_load_file($this->fileEdit);
    }

    public function save()
    {
        $xml = simplexml_load_file($this->fileEdit);
        $content = $this->input["content"];
        foreach ($content as $key => $value) {
            $xml->{$key} = $value;
        }
        $xml->saveXML($this->fileEdit);
        $this->info();
    }

    private function getPatches($items)
    {
        $result = array();
        $search = strtolower($this->input["searchText"]);
        foreach ($items as $item) {
            if (!empty($search)
                && (mb_strpos(strtolower($item->title), $search) !== false
                    || mb_strpos(strtolower($item["name"]), $search) !== false)
            ) {
                $result[] = ["id" => (string) $item["name"], "title" => (string) $item->title];
            }
        }
        return $result;
    }


    private function getTree($pages)
    {
        $oldLevel = 1;
        $thisMenu = [];
        $tmpLevel = [-1, -1, -1, -1, -1, -1];
        $tmpNode = [];

        if (!empty($pages))
            foreach ($pages as $value) {
                $level = (int)$value->level;
                $value = ["id" => (string) $value["name"], "title" => (string) $value->title];
                if ($level > $oldLevel)
                    $tmpLevel[$level] = -1;
                if ($level < $oldLevel)
                    $tmpLevel[$oldLevel] = -1;
                $tmpLevel[$level]++;
                if ($level == 1)
                    $tmpNode[$level] = &$thisMenu[$tmpLevel[$level]];
                else $tmpNode[$level] = &$tmpNode[$level - 1]["items"][$tmpLevel[$level]];
                $tmpNode[$level] = $value;
                $oldLevel = $level;
            }
        return $thisMenu;
    }

}