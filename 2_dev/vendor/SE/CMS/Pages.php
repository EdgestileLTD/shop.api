<?php

namespace SE\CMS;

class Pages extends Base
{
    //private $pathPages;
    //private $pathEdit;
    private $fileSource;
    private $fileEdit;
    //private $pathContent;
    private $lang = 'ru';
    private $isPage = true;
    private $pages = array();

    function __construct($input)
    {
        parent::__construct($input);

        if (!empty($this->input["name"])) {
            if ($this->input["name"] == 'index')
                $this->fileEdit = $this->projectFolder . "/edit/project.json";
            else
                $this->fileEdit = $this->projectFolder . "/edit/pages/{$this->input["name"]}.json";

            $this->isPage = $this->parsePageToJson($this->input["name"], 'edit');
        }
        $this->pages = $this->parsePagesToJson('edit');
    }

    public function delete()
    {
        $this->result = array();
        $data = array();
        if (file_exists($this->fileEdit)) {
            $data = json_decode(file_get_contents($this->fileEdit), true);
        }
        if (!empty($this->input["isSectionRemove"])) {
            foreach ($data['containers'] as $i => &$sections) {
                foreach ($sections['items'] as $ii => $section) {
                    if ($section['id'] == $this->input["id"])
                        unset($sections['items'][$ii]);
                }
            }
        }
        if (!empty($this->input["isObjectRemove"])) {
            foreach ($data['containers'] as $i => &$sections) {
                foreach ($sections['items'] as $ii => $section) {
                    if ($section['id'] == $this->input["sectionId"]){
                        unset($sections['items'][$ii]['objects'][$this->input["id"]]);
                    }
                }
            }
        }
        file_put_contents($this->fileEdit, json_encode($data));
        $this->info();
    }

    public function items()
    {
        $items = array();
        foreach($this->pages as $item){
            $items[] = array('name'=>$item['name'], 'title'=>$item['title']);
        }
        $this->result = array('items' => $items, 'count' => count($items));
    }

    public function fetch()
    {
        if (!empty($this->input["searchText"])) {
            $items = $this->getPatches($this->pages);
        } else {
            $items = $this->getTree($this->pages);
        }
        $items = array_merge(array(array('id' => 'index', 'title' => 'Глобальные разделы')), $items);
        $this->result = array('items' => $items, 'count' => count($items));
    }



    public function info()
    {
        $this->result = array();
        $data = array();
        if (file_exists($this->fileEdit)) {
            $data = json_decode(file_get_contents($this->fileEdit), true);
        }
        $this->emptyData($data);
        if (trim($this->input["isSection"])) {
            $this->result = $this->getSection($data, $this->input["id"]);
            return true;
        }
        if (trim($this->input["isObject"])) {
            writeLog($data);
            $section = $this->getSection($data, $this->input["sectionId"]);
            $this->result = $section['objects'][$this->input["id"]];
            return true;
        }
        $interface = array();
        $containers = $this->getContainers($data['map']);

        foreach ($data['containers'] as $item) {
            $items[$item['id']] = array();
            foreach ($item['items'] as $section) {
                $items[$item['id']][] = $this->getInfoSection($section);
                $interface[$section['type']] = $this->getInterface($section['type']);
            }
        }
        $data['containers'] = array();
        foreach ($containers as $cont_id) {
            if (empty($items[$cont_id])
                && ($this->isPage && $cont_id < 100 || !$this->isPage && $cont_id >= 100)
            ) {
                $items[$cont_id] = array();
            }
        }
        foreach ($items as $cont_id => $item) {
            $data['containers'][] = array('id' => $cont_id, 'items' => $item);
        }
        $this->result = array('item' => $data, 'interface' => $interface, 'maps' => $this->getMaps());//$this->parseXmlArr($xml);
    }

    public function save()
    {
        $data = array();
        $filePages = $this->projectFolder . "/edit/pages.json";
        if (empty($this->input["name"])) {
            if (!$this->input["title"]) return;

            $title = $this->input["title"];
            $name = strtolower(se_translite_url($title));
            $this->pages[] = array('name'=>$name, 'title'=>$title, 'level'=>1, 'mainlevel'=>0, 'skin'=>'default');
            $this->emptyData($data);
            $this->input["name"] = $name;
            $this->fileEdit = $this->pathEdit . "pages/{$name}.json";
        } else {
            if (file_exists($this->fileEdit)) {
                $data = json_decode(file_get_contents($this->fileEdit), true);
            }
            $this->emptyData($data);
            $content = $this->input["content"];
            if (empty($content)) return false;
            $fl_news = false;
            if ($this->input["isSection"] || $this->input["isSectionAdd"]) {
                // Сохраняем раздел
                if (empty($content['id'])) {
                    // Присваиваем новый ID разделу
                    $content['id'] = $this->newSectionId($data, $this->input["contId"]);
                    //writeLog($content, $data);
                    foreach ($data['containers'] as &$item) {
                        if ($item['id'] == $this->input["contId"]) {
                            $item['items'][] = array('id' => $content['id'], 'type' => 'atext');
                        }
                    }
                }
                $this->input["id"] = $content['id'];
                $tmpobj = &$this->getSection($data, $content['id']);

                foreach ($content as $name => $value) {
                    if ($name == 'objects') continue;
                    $tmpobj[$name] = $value;
                }
                $nobj = array();
                foreach ($content['objects'] as $obj_id => $object) {
                    if (empty($tmpobj['objects'][$obj_id])) {
                        //Новая секция
                        $nobj[$obj_id] = $object;
                    } else {
                        $nobj[$obj_id] = $tmpobj['objects'][$obj_id];
                    }
                }
                $tmpobj['objects'] = $nobj;
            } elseif ($this->input["isObject"] || $this->input["isObjectNew"]) {
                $section_id = $this->input["sectionId"];
                $sect = &$this->getSection($data, $section_id);
                if (empty($content['id'])) {
                    // Присваиваем новый ID разделу
                    $content['id'] = $this->newObjectId($data, $this->input["sectionId"]);
                    //$this->input["id"] = $content['id'];
                    $sect['objects'][$content['id']] = $content;
                }
                $this->input["id"] = $content['id'];
                //$tmpobj = &$section['objects'][$content['id']];
                foreach ($content as $name => $value) {
                    if ($name == 'objects') continue;
                    $sect['objects'][$content['id']][$name] = $value;
                }
                //$data['containers'][floor($section_id / 1000)][$section_id]['objects'][$content['id']] = $tmpobj;
            } else {

                foreach ($content as $name => $value) {
                    if ($name == 'containers' || $name == 'interface') continue;
                    $data[$name] = $value;
                }
                // оработка контейнеров
                /*
                $nconts = array();
                foreach ($content['containers'] as $i => $sections) {
                    $nconts[$i]['id'] = $sections['id'];
                    $nconts[$i]['items'] = array();
                    foreach ($sections['items'] as $section) {
                        $nconts[$i]['items'][] = $this->getSection($data, $section['id']);
                    }
                }
                //if (empty($nconts)) writeLog($content['containers'], $data['containers']);
                $data['containers'] = $nconts;*/
            }
            // Записываем модули в pages
            foreach($this->pages as &$page) {
                if ($page['name'] == $this->input["name"]) {
                    $page['title'] = $data['title'];
                    $page['skin'] = $data['map'];
                    $page['priority'] = $data['seo']['prioritypage'];
                    $page['modules'] = array();
                    foreach($data['containers'] as $container) {
                        foreach ($container['items'] as $section) {
                            $page['modules'][] = array('name' => $section['type'], 'id' => $section['id']);
                        }
                    }
                    break;
                }
            }
        }
        // Сохраняем страницы
        if (!($this->input["isSection"] || $this->input["isSectionAdd"]
            || $this->input["isObject"] || $this->input["isObjectAdd"])) {
            file_put_contents($filePages, json_encode($this->pages));
        }
        // Сохраняем страницу
        file_put_contents($this->fileEdit, json_encode($data));
        $this->info();
    }

    public function sort()
    {
        if (file_exists($this->fileEdit)) {
            $data = json_decode(file_get_contents($this->fileEdit), true);
            $this->emptyData($data);

            if ($this->input["isSection"]) {
                $tmpobj = &$this->getSection($data, $this->input["id"]);
                $oldItem = $tmpobj['objects'][$this->input["oldIndex"]];
                $newItem = $tmpobj['objects'][$this->input["newIndex"]];
                writeLog($oldItem);
                writeLog($newItem);
                $tmpobj['objects'][$this->input["newIndex"]] = $oldItem;
                $tmpobj['objects'][$this->input["oldIndex"]] = $newItem;
            }
            if ($this->input["isContainer"]) {
                $tmpobj = &$data['containers'][$this->input["id"]]['items'];
                $oldItem = $tmpobj[$this->input["oldIndex"]];
                $newItem = $tmpobj[$this->input["newIndex"]];
                $tmpobj[$this->input["newIndex"]] = $oldItem;
                $tmpobj[$this->input["oldIndex"]] = $newItem;
            }
            file_put_contents($this->fileEdit, json_encode($data));
            $this->result = 'ok';
        }

    }

    private function &getSection(&$data, $section_id)
    {
        foreach ($data['containers'] as &$item) {
            foreach ($item['items'] as &$sect) {
                if ($sect['id'] == $section_id) {
                    return $sect;
                }
            }
        }
    }

    private function newSectionId(&$data, $icont_id)
    {
        $max_id = 0;
        foreach ($data['containers'] as $item) {
            if ($item['id'] == $icont_id) {
                foreach ($item['items'] as $sect) {
                    if ($sect['id'] > $max_id) $max_id = $sect['id'];
                }
                if (!$max_id) {
                    $max_id = ($icont_id * 1000);
                }
                return $max_id + 1;
            }
        }
        $data['containers'][] = array('id' => $icont_id, 'items' => array());
        return ($icont_id * 1000) + 1;
    }

    private function newObjectId($data, $section_id)
    {
        $max_id = 0;
        foreach ($data['containers'] as $item) {
            foreach ($item['items'] as $section) {
                if ($section['id'] == $section_id) {
                    foreach ($section['objects'] as $obj_id => $object) {
                        if ($obj_id > $max_id) $max_id = $obj_id;
                    }
                    if (!$max_id) {
                        $max_id = ($section_id * 1000);
                    }
                    return $max_id + 1;
                }
            }
        }
    }

    private function getInfoSection($section)
    {
        $result = array();
        foreach ($section as $name => $val) {
            if ($name == 'id' || $name == 'type' || $name == 'title' || $name == 'objects') {
                $result[$name] = $val;
            }
            if ($name == 'objects') {
                foreach ($val as $id => $obj) {
                    $result[$name][$id] = $this->infoObject($obj, array('contentType' => $section['type'], 'sectionId' => $section['id']));
                }
            }
        }
        return $result;
    }

    private function emptyData(&$data)
    {
        if (empty($data)) {
            foreach($this->pages as $page) {
                if ($page['name'] == $this->input["name"]) {
                    $data['title'] = $page['title'];
                    $data['map'] = 'default';
                    $data['vars'] = array();
                    $data['header'] = '';
                    $data['access']['groupsname'] = '';
                    $data['access']['groupslevel'] = '';
                    $data['seo']['title'] = '';
                    $data['seo']['keywords'] = '';
                    $data['seo']['description'] = '';
                    $data['seo']['prioritypage'] = 5;
                    $data['containers'] = array();
                    break;
                }
            }
        }
    }


    private function infoObject($obj, $oarr = array())
    {
        $arr = array();
        foreach ($obj as $name => $val) {
            if (in_array($name, array('id', 'field', 'title', 'image')))
                $arr[$name] = strval($val);
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

        $option = $params = $types = $objects = array();
        $path = DOCUMENT_ROOT . $this->getFolderModule($type);
        if (file_exists($path . '/' . $type . '/property/option.xml')) {
            $option = simplexml_load_file($path . '/' . $type . '/property/option.xml');
        }
        if (file_exists($path . '/' . $type . '/interface/structure.xml')) {
            $structure = simplexml_load_file($path . '/' . $type . '/interface/structure.xml');
        }
        if (file_exists($path . '/' . $type . '/property/param_' . $this->lang . '.xml')) {
            $plist = simplexml_load_file($path . '/' . $type . '/property/param_' . $this->lang . '.xml');
            foreach ($plist->parametr as $id => $it) {
                $list = array();
                if (strpos(strval($it['list']), '|') !== false) {
                    $field_type = 'select';
                    foreach (explode(',', strval($it['list'])) as $its) {
                        list($name, $val) = explode('|', $its);
                        if (!$val) {
                            $val = $name;
                        }
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
                    'label' => htmlspecialchars_decode($it['title']),
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
        foreach ($structure->objects->field as $field) {
            $name = strval($field['name']);
            $type = strval($field['type']);
            $label = strval($field->title);

            if (($name == 'note' || $name == 'text') && $type == 'text') {
                $type = 'textarrayedit';
            }

            if ($name == 'title' && !$label) {
                $label = 'Заголовок';
            }
            if ($name == 'note' && !$label) {
                $label = 'Краткий текст';
            }
            if ($name == 'text' && !$label) {
                $label = 'Содержание';
            }

            $objects['tabs']['tab_content']['title'] = 'Содержание';


            $objects['tabs']['tab_content']['fields'][$name] = array('label' => $label, 'type' => $type);
            if (strval($field['type']) == 'menu') {
                $mlist = explode(',', strval($field->list));
                $list = array();
                foreach ($mlist as $item) {
                    list($val, $lab) = explode('|', $item);
                    $list[] = array('label' => (($lab) ? $lab : $val), 'value' => $val);
                }

                $objects['tabs']['tab_content']['fields'][$name]['list'] = $list;
                list($def,) = explode('|', strval($field->default));
                $objects['tabs']['tab_content']['fields'][$name]['default'] = $def;
            }
        }

        return
            array(
                'tabs' => $tabs,
                'option' => $option,
                'info' => $types,
                'objects' => $objects
            );
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
        $thisMenu = array();
        $tmpLevel = [-1, -1, -1, -1, -1, -1];
        $tmpNode = array();

        if (!empty($pages))
            foreach ($pages as $value) {
                $level = (int)$value['level'];
                if ($level < 1) $level = 1;
                $value = array("id" => $value["name"], "title" => $value['title']);
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

    private function getContainers($mapfile)
    {
        if (!$mapfile) $mapfile = 'default';
        if (!is_dir($this->pathEdit . "maps/"))
            mkdir($this->pathEdit . "maps/", 0700, 1);
        if (!file_exists($this->pathEdit . "maps/" . $mapfile . ".json")
        ) {
            $containers = array();
            $cont = file_get_contents($this->pathContent . '/skin/' . $mapfile . '.map');
            preg_match_all("/\[(content|global)-([\d]+)\]/", $cont, $out, PREG_SET_ORDER);
            foreach ($out as $it) {
                $containers[] = ($it[1] == 'global') * 100 + $it[2];
            }
            file_put_contents($this->pathEdit . "maps/" . $mapfile . ".json", json_encode($containers));
        } else {
            $containers = json_decode(file_get_contents($this->pathEdit . "maps/" . $mapfile . ".json"), true);
        }
        return $containers;
    }

    private function getMaps($mapfile)
    {
        $maps = glob($this->pathContent . '/skin/*.map');
        $result = array();
        foreach ($maps as $map) {
            $result[] = substr(basename($map), 0, -4);
        }
        return $result;
    }

    private function isFindPage($name)
    {
        $filePages = $this->projectFolder . "/edit/pages.json";
        $items = array();
        if (file_exists($filePages)) {
            $items = json_decode(file_get_contents($filePages), true);
        }
        foreach ($items as $item) {
            if ($name == $item['name']) {
                return true;
            }
        }
    }
}
