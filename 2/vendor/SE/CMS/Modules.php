<?php

namespace SE\CMS;

class Modules extends Base
{
    private $root = DOCUMENT_ROOT;
    private $lang = 'ru';

    function __construct($input)
    {

    }

    public function fetch()
    {
        $items = array();
        //$items[] = array('title' => 'Текст', 'icon' => '', 'modules' => array(array('type'=>'atext', 'title' => 'Текст и рисунок', 'description' => 'Адаптивный текст и рисунок', 'icon' => '')));
        //$items[] = array('title' => 'Обратная связь', 'icon' => '', 'modules' => array(array('type'=>'amail','title' => 'Форма обратной связи', 'description' => 'Форма обратной связи', 'icon' => '')));
        $groups = $this->getModules();
        foreach($groups as $gname=>$gr) {
            $items[] = array('title'=>$gname, 'modules'=>$gr['title']);
        }

        $this->result = array('items' => $items, 'count' => count($items), 'test' => true);
    }

    private function getModules()
    {
        $groups = array();
        $mlist = array();
        if (file_exists($this->root . '/projects/modules.dat')) {
            $ml = file($this->root . '/projects/modules.dat');
            foreach ($ml as $m) {
                $mlist[] = trim($m);
            }
        }
        foreach ($this->pages as $page) {
            foreach ($page->modules as $m) {
                $m = trim($m['name']);
                if ($m && !in_array($m, $mlist)) {
                    $mlist[] = $m;
                }
            }
        }


        $d = opendir($this->root . '/lib/modules');
        $modules = array();
        while (($f = readdir($d)) !== false) {
            if ($f == '.' || $f == '..') continue;
            if (strpos($f, 'mdl_') !== false && getExtFile($f) == 'php') {
                $f = delExtFile($f);
                $name = substr($f, 4, strlen($f) - 4);
                //if (empty($mlist) || !in_array($name, $mlist)) {
                $modules[] = $name;
                //}

            }
        }
        closedir($d);
        $d = opendir($this->root . '/modules');
        while (($f = readdir($d)) !== false) {
            if ($f == '.' || $f == '..') continue;
            if (strpos($f, 'mdl_') !== false && getExtFile($f) == 'php') {
                $f = delExtFile($f);
                $name = substr($f, 4, strlen($f) - 4);
                if (!in_array($name, $modules)) {
                    $modules[] = $name;
                }
            }
        }

        closedir($d);
        $modules = array_unique($modules);
        foreach ($modules as $module) {
            $root = $this->root . $this->getFolderModule($module) . '/' . strval($module) . '/property/types_' . $this->lang . '.xml';
            if (file_exists($root)) {
                $property = simplexml_load_file($root);
                $groups[strval($property->group)]['title'][] = array('type' => strval($module), 'title' => strval($property->name));
                $groups[strval($property->group)]['icon'] = '';
            } else {
                $groups['Base']['title'][] = array('type' => $module, 'title' => $module);
                $groups['Base']['icon'] = '';
            }
        }
        return $groups;
    }

    private function getFolderModule($type)
    {
        $pathalt = '/lib';
        $path = '/modules';

        if (file_exists($this->root . $pathalt . $path . '/module_' . $type . '.class.php')
            || file_exists($this->root . $pathalt . $path . '/mdl_' . $type . '.php')
        ) {
            return $pathalt . $path;
        } else
            if (file_exists($this->root . $path . '/module_' . $type . '.class.php')
                || file_exists($this->root . $path . '/mdl_' . $type . '.php')
            ) {
                return $path;
            }
        return;
    }
}