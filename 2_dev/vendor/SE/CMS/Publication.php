<?php

namespace SE\CMS;

class Publication extends Base
{

    function __construct($input)
    {
        parent::__construct($input);

    }

    public function save()
    {
        $this->parsePagesToJson('arhiv');
        $fileSource = $this->projectFolder . "/edit/pages.json";
        $filePages = $this->projectFolder . "/pages.xml";

        if (file_exists($fileSource)) {
            $pages = json_decode(file_get_contents($fileSource), true);
            $xml = $this->parseArrPages($pages);
            $xml->saveXML($filePages);
        }
        if (file_exists($this->projectFolder . '/edit/project.json')) {
            $this->parsePageToJson('index', 'arhiv');
            $data = json_decode(file_get_contents($this->projectFolder . '/edit/project.json'), true);
            $xml = $this->parseArrProject($data);
            $xml->saveXML($this->projectFolder . '/project.xml');
        }
        $pagelist = glob($this->projectFolder . "/edit/pages/*.json");
        foreach($pagelist as $fpage) {
            $namepage = basename($fpage, '.json');
            $this->parsePageToJson($namepage, 'arhiv');
            $data = json_decode(file_get_contents($fpage), true);
            $xml = $this->parseArrPage($data);
            $xml->saveXML($this->projectFolder . "/pages/" . $namepage . '.xml');
        }
        $this->storeMenuList();



        $this->result = array('result' => $filePages);
    }

    public function cancel()
    {

    }

    private function parseArrPages($pages)
    {
        $xml = simplexml_load_string('<?xml version="1.0" encoding="utf-8" ?><pages></pages>');
        foreach ($pages as $i => $page) {
            $this->setPages($xml->page[$i], $page);
        }
        return $xml;

    }

    private function setPages(&$xml, $arr, $filtr = '')
    {
        if ($arr['name']) {
            $xml['name'] = $arr['name'];
        }
        if ($filtr == 'modules') {
            if ($arr['id']) {
                $xml['id'] = $arr['id'];
            }
        }
        foreach ($arr as $name => $val) {
            if ($name == 'name') continue;
            if ($filtr == 'modules' && $name == 'id') continue;
            if (is_array($val)) {
                foreach($val as $ii=>$v) {
                    $ixml = &$xml->{$name};
                    $this->setPages($ixml[$ii], $v, $name);
                }
            } else
                $xml->$name = strval($val);
        }
    }

    private function storeMenuList()
    {
        $menulist = array('mainmenu', 'pagemenu');
        $pathMenu = $this->projectFolder . "/edit/menu/";
        $mlist = glob($pathMenu . '*.json');
        foreach($mlist as $filemenu){
            $namemenu = basename($filemenu, '.json');
            $items = json_decode(file_get_contents($filemenu), true);
            if (in_array($namemenu, $menulist)) {
                // Стандартное меню
            } else {
                // mmit
                $fileNewMenu = $this->pathContent . '/skin/' . $namemenu . '.mmit';
                file_put_contents($fileNewMenu, "Site menu\t0\t0\r\n" . $this->menuItemsFile($items));
                if (file_exists($fileNewMenu . '.log')) {
                    unlink($fileNewMenu . '.log');
                }
            }
        }
    }

    private function menuItemsFile($items, $level = 0)
    {
        $menu = "";
        foreach($items as $item) {
            $name = $item['url'] . (($item['target']) ? chr(8).$item['target'] : '');
            $menu .= "{$level}\t{$name}\t{$item['title']}|{$item['subtitle']}\t{$item['image']}\t{$item['imageactive']}\t{$item['success']}\r\n";

            if (!empty($item['items'])) {
                $menu .= $this->menuItemsFile($item['items'], $level + 1);
            }
        }
        return $menu;
    }
}