<?php

namespace SE\CMS;

class Menus extends Base
{
    private $root = DOCUMENT_ROOT;
    private $lang = 'ru';
    private $menulist = array('mainmenu', 'pagemenu');

    function __construct($input)
    {
        parent::__construct($input);
        //$this->projectFolder = DOCUMENT_ROOT . '/projects/' . (($this->seFolder) ? $this->seFolder . '/' : '');
        //$this->pathEdit = $this->projectFolder . "/edit/menu/";
        $this->pathContent = DOCUMENT_ROOT . (($this->seFolder) ? '/' . $this->seFolder : '');

        if (!is_dir($this->pathEdit.'menu/'))
            mkdir($this->pathEdit . 'menu/', 0700, 1);

        foreach ($this->menulist as $menuname) {
            if (!file_exists($this->pathEdit .'menu/' . $menuname . '.json') && file_exists($this->projectFolder . '/' . $menuname . '.xml')
                || filemtime($this->projectFolder . '/' . $menuname . '.xml') > filemtime($this->pathEdit .'menu/'. $menuname . '.json')
            ) {
                $xml = simplexml_load_file($this->projectFolder . '/' . $menuname . '.xml');
                file_put_contents($this->pathEdit .'menu/'. $menuname . '.json', json_encode($this->parseMunuXmlArr($xml)));
            }
        }

        $mlist = glob($this->pathContent . '/skin/*.mmit');
        foreach ($mlist as $filemenu) {
            $menuname = basename($filemenu, '.mmit');
            if (!file_exists($this->pathEdit .'menu/'. $menuname . '.json') && file_exists($filemenu)
                || filemtime($filemenu) > filemtime($this->pathEdit .'menu/'. $menuname . '.json')
            ) {
                $menu = file_get_contents($filemenu);
                file_put_contents($this->pathEdit .'menu/'. $menuname . '.json', json_encode($this->getTree($menu)));
            }
        }
    }

    public function items(){
        $items = array();
        $mlist = glob($this->pathEdit .'menu/'. '*.json');
        foreach($mlist as $filemenu) {
            $menuname = basename($filemenu, '.json');
            $items[] = array('name'=>$menuname, 'title'=>$menuname);
        }
        $this->result = array('items' => $items, 'count' => count($items));
    }

    public function fetch()
    {
        if ($this->input["name"]) {
            $items = array();
            if (file_exists($this->pathEdit .'menu/'. $this->input["name"] . '.json')){
                $items = json_decode(file_get_contents($this->pathEdit .'menu/'. $this->input["name"] . '.json'), true);
            }
            $this->result = array('items' => $items);
        }
    }

    public function info()
    {

    }

    public function delete()
    {
        if ($this->input['name']) {
            $isDelete = false;
            foreach($this->input['ids'] as $id) {
                if (file_exists($this->pathEdit .'menu/'. $this->input["name"] . '.json')){
                    $items = json_decode(file_get_contents($this->pathEdit .'menu/'. $this->input["name"] . '.json'), true);
                }

                $it = $this->unsetItem($items, $id);
                if (!empty($it)){
                    //writeLog($id, $it);
                    $isDelete = true;
                    writeLog($items);
                }
            }
            if ($isDelete) {
                file_put_contents($this->pathEdit .'menu/'. $this->input['name'] . '.json', json_encode($items));
            }
        }
        $this->fetch();
    }

    private function unsetItem(&$items, $name) {
        $result = false;
        foreach($items as $i=>$item) {
            if (trim($item['name']) == trim($name)) {
                array_splice($items, $i, 1);
                return true;
            }
            if (!empty($item['items'])) {
                $result = $this->unsetItem($items[$i]['items'], $name);
                if (!empty($result)) {
                    return $result;
                }
            }
        }
        return false;
    }

    private function getTree($pages)
    {
        $oldLevel = 1;
        $thisMenu = array();
        $tmpLevel = [-1, -1, -1, -1, -1, -1, -1, -1];
        $tmpNode = array();
        $pages = trim($pages);

        if (!empty($pages))
            foreach (explode("\n", $pages) as $i => $value) {
                $value = trim($value);
                if ($i == 0) continue;
                $value = explode("\t", $value);
                $level = $value[0] + 1;
                $value[1] = (!empty($value[1])) ? $value[1] : '';
                list($title, $stitle) = explode("|", $value[2]);
                @list($url, $target) = explode(chr(8), $value[1]);
                $name = (strtolower(se_translite_url($url))) ? strtolower(se_translite_url($url)) : strtolower(se_translite_url($title));
                $val = array(
                    "name" => $name,
                    "title" => $title,
                    "subtitle" => $stitle,
                    "url" => $url,
                    "target" => $target,
                    "level" => $level,
                    "success" => $value[5],
                    "image" => (!empty($value[3])) ? $value[3] : '',
                    "imageactive" => (!empty($value[4])) ? $value[4] : '',
                    "imagehover" => ''
                );
                if ($level > $oldLevel)
                    $tmpLevel[$level] = -1;
                if ($level < $oldLevel)
                    $tmpLevel[$oldLevel] = -1;
                $tmpLevel[$level]++;
                if ($level == 1)
                    $tmpNode[$level] = &$thisMenu[$tmpLevel[$level]];
                else $tmpNode[$level] = &$tmpNode[$level - 1]["items"][$tmpLevel[$level]];
                $tmpNode[$level] = $val;
                $oldLevel = $level;
            }
        return $thisMenu;
    }

    private function parseMunuXmlArr($xml)
    {
        $result = array();
        foreach ($xml as $item) {
            $val = array(
                "name" => strval($item->name),
                "title" => strval($item->title),
                "subtitle" => strval($item->subtitle),
                "url" => strval($item->url),
                "target" => strval($item->target),
                "level" => strval($item->level),
                "success" => strval($item->success),
                "image" => strval($item->image),
                "imageactive" => strval($item->imageactive),
                "imagehover" => strval($item->imagehover),
            );
            if (!empty($item->items)) {
                $val['items'] = $this->parseXmlArr($item->items);
            }
            $result[] = $val;
        }
        return $result;
    }

}