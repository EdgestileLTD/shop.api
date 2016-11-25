<?php

namespace SE\CMS;

class Pages extends Base
{
    private $pathPages;
    private $pathEdit;
    private $fileSource;
    private $fileEdit;

    function __construct($input)
    {
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
        if (!empty($this->input["searchText"])) {
            $items = $this->getPatches($pages);
        } else {
            $items = $this->getTree($pages);
        }
        $this->result = array('items' => $items, 'count' => count($items));
    }

    public function info()
    {
        $xml = simplexml_load_file($this->fileEdit);
        //writelog($xml);
        $this->result = $this->parseXmlArr($xml);
    }

    private function parseXmlArr($xml)
    {
        $data = array();
        $data['title'] = (!empty($xml->title)) ? strval($xml->title) : '';
        $data['map'] = (!empty($xml->css)) ? strval($xml->css) : 'default';
        $data['header'] = (!empty($xml->head)) ? strval($xml->head) : '';
        $data['access']['groupsname'] = (!empty($xml->groupsname)) ? strval($xml->groupsname) : '';
        $data['access']['groupslevel'] = (!empty($xml->groupslevel)) ? strval($xml->groupslevel) : '';
        $data['seo']['title'] = (!empty($xml->titlepage)) ? strval($xml->titlepage) : '';
        $data['seo']['keywords'] = (!empty($xml->keywords)) ? strval($xml->keywords) : '';
        $data['seo']['description'] = (!empty($xml->description)) ? strval($xml->description) : '';
        $data['seo']['prioritypage'] = (!empty($xml->prioritypage)) ? intval($xml->prioritypage) : 5;
        $data['vars'] = array();
        if (!empty($xml->vars))
        foreach($xml->vars as $n=>$v){
            $data['vars'][$n] = $v;
        }
        $data['containers'] = array();
        if (count($xml->sections)) {
            foreach ($xml->sections as $value) {
                list($id_content,) = explode('.', strval($value['name']));
                $data['containers'][floor($id_content / 1000)][strval($value['name'])] = $this->getObject($value);
            }
        }
        return $data;
    }

    private function parseArrXml($arr)
    {
        $xml = simplexml_load_string("<?xml version='1.0'?><page></page>");
        $xml->groupsname = $arr['success']['groupsname'];
        $xml->groupslevel = $arr['success']['groupslevel'];
        $xml->css = $arr['map'];
        $xml->head = $arr['header'];
        $xml->title = $arr['title'];
        $xml->titlepage = $arr['seo']['title'];
        $xml->keywords = $arr['seo']['keywords'];
        $xml->description = $arr['seo']['description'];
        $xml->prioritypage = $arr['seo']['prioritypage'];
        foreach($arr['vars'] as $n=>$v){
            $xml->vars->$n = $v;
        }
        $i = 0;
        foreach($arr['containers'] as $cont=>$sect){
            foreach($sect as $id_sect=>$section){
                $xml->sections[$i]['name'] = strval($id_sect);
                $this->setObject($xml->sections[$i], $section);
                $i++;
            }
        }
        return $xml;
    }


    private function getObject($obj, $nobj = 'sections')
    {
        $arr = array();
        foreach($obj as $name=>$val){
            if ($nobj == 'sections' && ($name == 'image' || $name == 'image_title' || $name == 'image_alt')) {
                continue;
            }
            if ($name == 'objects') {
                $arr['objects'][strval($val['name'])] = $this->getObject($val, $name);
                continue;
            }
            if ($name == 'parametrs') {
                $arr['parametrs'] = $this->getObject($val, $name);
                continue;
            }
            if ($name == 'translates') {
                $arr['translates'] = $this->getObject($val, $name);
                continue;
            }
            if (!empty($val->children())) {
                foreach($val as $n1=>$v1){
                    $arr[$name][$n1] = $this->getObject($v1, $name);
                }
            } else {
                $arr[$name] = strval($val);
            }
        }
        if ($nobj == 'sections') {
            $arr['images'] = array();
            if (empty($obj->image)){
                $arr['images'][] = array('image' => $obj->image, 'title' => $obj->image_title, 'alt' => $obj->image_alt);
            }
        }
        return $arr;
    }

    private function setObject(&$xml, $arr)
    {
        //$xml['name'] = $arr['id'];
        $obj = $xml;
        foreach($arr as $name=>$val){

            if ($name == 'images') {
                if (!empty($val['images'][0])) {
                    $obj->image = $val['images'][0]['image'];
                    $obj->image_title = $val['images'][0]['image_title'];
                    $obj->image_alt = $val['images'][0]['image_title'];
                }
                continue;
            }


            if ($name == 'objects') {
                $i = 0;
                foreach($val as $id=>$ob){
                    //$objs = new stdClass;
                    $obj->objects[$i]['name'] = $id;
                    foreach($ob as  $n=>$v) {
                        $obj->objects[$i]->$n = $v;
                    }
                    $i++;
                }
                continue;
            }


            if ($name == 'parametrs') {
                //$objs = new stdClass;
                foreach($val as $n=>$v){
                    $obj->parametrs->$n = $v;
                }
                continue;
            }
            if ($name == 'translates') {
                //$objs = new stdClass;
                foreach($val as $n=>$v){
                    $obj->translates->$n = $v;
                }
                //$obj->translates = $objs;
                continue;
            }

            $obj->$name = strval($val);
        }
    }

    public function save()
    {
        //$xml = simplexml_load_file($this->fileEdit);
        $content = $this->input["content"];
        $json = json_encode($content);
        file_put_contents($this->pathEdit . "pages/{$this->input['name']}.json", $json);
        /*
        foreach ($content as $key => $value) {
            $xml->{$key} = $value;
        }*/
        $xml = $this->parseArrXml($content);
        writeLog($this->input);
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
                $result[] = ["id" => (string)$item["name"], "title" => (string)$item->title];
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
                $value = ["id" => (string)$value["name"], "title" => (string)$value->title];
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