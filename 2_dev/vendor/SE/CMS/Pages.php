<?php

namespace SE\CMS;

class Pages extends Base
{
    private $pathPages;
    private $pathEdit;
    private $fileSource;
    private $fileEdit;
    private $lang = 'ru';

    function __construct($input)
    {
        parent::__construct($input);
        $this->projectFolder = DOCUMENT_ROOT . '/projects/www';
        $this->pathPages = $this->projectFolder . "/pages/";
        $this->pathEdit = $this->projectFolder . "/edit/";
        if (!is_dir($this->pathEdit . "pages/"))
            mkdir($this->pathEdit . "pages/", 0700, 1);
        if (!empty($this->input["name"])) {
            $this->fileSource = $this->pathPages . "{$this->input["name"]}.xml";
            $this->fileEdit = $this->pathEdit . "pages/{$this->input["name"]}.json";
            if (!file_exists($this->fileEdit) && file_exists($this->fileSource)){
                $xml = simplexml_load_file($this->fileSource);
                //print_r($xml);
                file_put_contents($this->fileEdit, json_encode($this->parseXmlArr($xml)));
            }
                //copy($this->fileSource, $this->fileEdit);
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
        $this->result = array();
        $data = array();
        if (file_exists($this->fileEdit)) {
            $data = json_decode(file_get_contents($this->fileEdit), true);
        }
          //  $xml = simplexml_load_file($this->fileEdit);
        //writelog($xml);
        //writeLog(trim($this->input["isSection"]));
        if (trim($this->input["isSection"])) {
            foreach($data['containers'] as $cont_id => $sections) {
                foreach($sections as $section_id => $section) {
                    if (strval($this->input["id"]) == $section_id) {
                        $this->result = $section;
                        return true;
                    }
                }
            }
            return false;
        }
        foreach($data['containers'] as $cont_id => $sections) {
             foreach($sections as $section_id => $section) {
                 foreach($section as $name=>$val) {
                     if ($name !== 'id' && $name !== 'type' && $name !== 'title' && $name !== 'objects') {
                         unset($data['containers'][$cont_id][$section_id][$name]);
                     }
                 }
                 $data['interface'][$section['type']] = $this->getInterface($section['type']);
             }

        }
        $this->result = $data;//$this->parseXmlArr($xml);
    }

    public function save()
    {
        //$xml = simplexml_load_file($this->fileEdit);
        if (file_exists($this->fileEdit)) {
            $data = json_decode(file_get_contents($this->fileEdit), true);
        }

        $content = $this->input["content"];

        $json = json_encode($content);
        if (trim($this->input["isSection"])) {
            // Сохраняем раздел
            if (empty($content['id'])) {
                // Присваиваем новый ID разделу
                $content['id'] = $this->newSectionId($data, $this->input["idConteiner"]);
            }


            $data['containers'][floor($content['id'] / 1000)][$content['id']] = $content;
        } else {
            foreach($content as $name=>$value) {
                $data[$name] = $value;
                if ($name == 'containers') continue;
            }
            // оработка контейнеров
            $nconts = array();
            foreach($content['containers'] as $cont_id=>$sections) {
                foreach($sections as $section_id=>$section) {
                    if (empty($data['containers'][$cont_id][$section_id])) {
                        //Новая секция
                        $nconts[$cont_id][strval($section_id)] = $section;
                    } else {
                        $nconts[$cont_id][strval($section_id)] = $data['containers'][$cont_id][$section_id];
                    }
                }
            }
            $data['containers'] = $nconts;
        }
        file_put_contents($this->fileEdit, json_encode($data));
        /*
        foreach ($content as $key => $value) {
            $xml->{$key} = $value;
        }*/
        //$xml = $this->parseArrXml($content);
        //writeLog($this->input);
        //$xml->saveXML($this->fileEdit);
        //$this->info();
    }

    private function newSectionId($data, $icont_id)
    {
        $max_id = 0;
        foreach($data['containers'] as $cont_id=>$sections) {
            if ($cont_id !== $icont_id ) continue;
            if (count($sections)) {
                foreach ($sections as $section_id => $section) {
                    if ($section_id > $max_id) $max_id = $section_id;
                }
            }
        }
        if (!$max_id) {
            $max_id = ($icont_id * 1000);
        }
        return $max_id + 1;
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
            foreach ($xml->vars as $n => $v) {
                //if (!empty($v))
                    $data['vars'][$n] = $v;
            }
        $data['containers'] = array();
        if (count($xml->sections)) {
            foreach ($xml->sections as $value) {
                list($id_content,) = explode('.', strval($value['name']));
                $data['containers'][floor($id_content / 1000)][strval($value['name'])] = $this->getObject($value);
                //$data['interface'][strval($value->type)] = $this->getInterface(strval($value->type));
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
        foreach ($arr['vars'] as $n => $v) {
            $xml->vars->$n = $v;
        }
        $i = 0;
        foreach ($arr['containers'] as $cont => $sect) {
            foreach ($sect as $id_sect => $section) {
                $xml->sections[$i]['name'] = strval($id_sect);
                $this->setObject($xml->sections[$i], $section);
                $i++;
            }
        }
        return $xml;
    }

    private function infoObject($obj, $nobj = 'sections', $oarr = array())
    {
        $arr = array();
        foreach ($obj as $name => $val) {
            if ($nobj == 'sections' && ($name == 'image' || $name == 'image_title' || $name == 'image_alt')) {
                continue;
            }
            if ($name == 'objects') {
                $arr['objects'][strval($val['name'])] = $this->infoObject($val, $name, array('content_type' => strval($obj->type), 'section_id' => strval($obj->id)));
                continue;
            }
            if ($name == 'parametrs') {
                continue;
            }
            if ($name == 'translates') {
                continue;
            }
            if (!empty($val->children())) {
                foreach ($val as $n1 => $v1) {
                    //$arr[$name][$n1] = $this->infoObject($v1, $name, array('content_type'=>strval($obj->type)));
                }
            } else {
                if (in_array($name, array('id', 'type', 'title')))
                    $arr[$name] = strval($val);
            }
        }
        if (!empty($oarr))
            foreach ($oarr as $k3 => $v3)
                $arr[$k3] = $v3;
        return $arr;
    }


    private function getInterface($type)
    {
        // Поля для раздела
        //  'title'=>array('label'=>'Заголовок раздела', 'default'=>'', 'type'=>'text', 'length'=>'', 'list'=>array(), 'placeholder'=>'', infotext=''),

        $option = $params = $types = array();
        $path = DOCUMENT_ROOT . $this->getFolderModule($type);
        if (file_exists($path . '/' . $type . '/property/option.xml')) {
            $option = simplexml_load_file($path . '/' . $type . '/property/option.xml');
        }
        if (file_exists($path . '/' . $type . '/property/param_' . $this->lang . '.xml')) {
            $plist = simplexml_load_file($path . '/' . $type . '/property/param_' . $this->lang . '.xml');
            foreach ($plist->parametr as $id => $it) {
                $list = array();
                if (strpos(strval($it['list']), '|') !== false) {
                    $field_type = 'select';
                    foreach (explode(',', strval($it['list'])) as $its) {
                        list($name, $val) = explode('|', $its);
                        $list[] = array('value' => $name, 'label' => $val);
                    }
                } else {
                    $field_type = 'text';
                }
                if (strpos(strval($it['title']), ':::') !== false) {
                    $field_type = 'label';
                }
                list($def,) = explode('|', strval($it));


                $params[strval($it['name'])] = array(
                    'label' => strval($it['title']),
                    'list' => $list,
                    'default' => $def,
                    'type' => $field_type
                );

            }
        }
        if (file_exists($path . '/' . $type . '/property/types_' . $this->lang . '.xml')) {
            $types = simplexml_load_file($path . '/' . $type . '/property/types_' . $this->lang . '.xml');
        }
        $fields_content = array(
            'title' => array('label' => 'Заголовок раздела', 'type' => 'text'),
            'text' => array('label' => 'Содержание', 'type' => 'textareaedit'),
        );
        $fields_images = array(
            'image' => array('label' => 'Изображение', 'type' => 'fileimage'),
            'image_alt' => array('label' => 'Алтернативный текст'),
            'image_title' => array('label' => 'Текст при наведении'),
            'image_size' => array('label' => 'Размер изображения (100x100)', 'infotext' => 'px')
        );
        $fields_params = array(
            'image' => array('label' => 'Изображение', 'type' => 'fileimage'),
            'image_alt' => array('label' => 'Алтернативный текст'),
            'image_title' => array('label' => 'Текст при наведении'),
            'image_size' => array('label' => 'Размер изображения (100x100)', 'infotext' => 'px')
        );
        $tabs = array(
            'tab_content' => array(
                'title' => 'Содержание',
                'fields' => $fields_content
            ),
            'tab_images' => array(
                'title' => 'Изображение',
                'fields' => $fields_images
            ),
            'tab_params' => array(
                'title' => 'Параметры',
                'fields' => $params
            )
        );
        return
            array(
                'tabs' => $tabs,
                'option' => $option,
                'info' => $types
            );
    }

    private function getObject($obj, $nobj = 'sections')
    {
        $arr = array();
        foreach ($obj as $name => $val) {
            if ($nobj == 'sections' && ($name == 'image' || $name == 'image_title' || $name == 'image_alt')) {
                continue;
            }
            if ($name == 'objects') {
                $arr['objects'][strval($val['name'])] = $this->getObject($val, $name);
                continue;
            }
            if ($name == 'parametrs') {
                $arr['params'] = $this->getObject($val, $name);
                continue;
            }
            if ($name == 'translates') {
                $arr['translates'] = $this->getObject($val, $name);
                continue;
            }
            if (!empty($val->children())) {
                foreach ($val as $n1 => $v1) {
                    $arr[$name][$n1] = $this->getObject($v1, $name);
                }
            } else {
                $arr[$name] = strval($val);
            }
        }
        if ($nobj == 'sections') {
            $arr['images'] = array();
            if (empty($obj->image)) {
                $arr['images'][] = array('image' => $obj->image, 'title' => $obj->image_title, 'alt' => $obj->image_alt);
            }
        }
        return $arr;
    }

    private function setObject(&$xml, $arr)
    {
        //$xml['name'] = $arr['id'];
        $obj = $xml;
        foreach ($arr as $name => $val) {

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
                foreach ($val as $id => $ob) {
                    //$objs = new stdClass;
                    $obj->objects[$i]['name'] = $id;
                    foreach ($ob as $n => $v) {
                        $obj->objects[$i]->$n = $v;
                    }
                    $i++;
                }
                continue;
            }


            if ($name == 'params') {
                //$objs = new stdClass;
                foreach ($val as $n => $v) {
                    $obj->parametrs->$n = $v;
                }
                continue;
            }
            if ($name == 'translates') {
                //$objs = new stdClass;
                foreach ($val as $n => $v) {
                    $obj->translates->$n = $v;
                }
                //$obj->translates = $objs;
                continue;
            }

            $obj->$name = strval($val);
        }
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

    private function getFolderModule($type)
    {
        $pathalt = '/lib';
        $path = '/modules';

        if (file_exists(DOCUMENT_ROOT . $pathalt . $path . '/module_' . $type . '.class.php')
            || file_exists(DOCUMENT_ROOT . $pathalt . $path . '/mdl_' . $type . '.php')
        ) {
            return $pathalt . $path;
        } else
            if (file_exists(DOCUMENT_ROOT . $path . '/module_' . $type . '.class.php')
                || file_exists(DOCUMENT_ROOT . $path . '/mdl_' . $type . '.php')
            ) {
                return $path;
            }
        return;
    }
}