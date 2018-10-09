<?php

namespace SE\Shop;

use SE\DB as DB;

class SeoVariable extends Base
{
    protected $tableName = "shop_variables";
    protected $limit = null;

    public function fetch()
    {
        $vars = array();

        if ($this->input["type"] == 'goods') {
            $var['name'] = '{name}';
            $var['note'] = 'наименование товара со склонением';
            $vars[] = $var;
            $var['name'] = '{asname}';
            $var['note'] = 'наименование товара без склонения';
            $vars[] = $var;
            $var['name'] = '{nameup}';
            $var['note'] = 'наименование товара со склонением с сохранением регистра';
            $vars[] = $var;
            $var['name'] = '{asnameup}';
            $var['note'] = 'наименование товара без склонения с сохранением регистра';
            $vars[] = $var;
            $var['name'] = '{производитель}';
            $var['note'] = 'наименование бренда товара';
            $vars[] = $var;
            $var['name'] = '{цена}';
            $var['note'] = 'цена товара с учетом скидки';
            $vars[] = $var;
            $var['name'] = '{старая цена}';
            $var['note'] = 'цена товара без учета скидки';
            $vars[] = $var;
            $var['name'] = '{скидка}';
            $var['note'] = 'процент скидки';
            $vars[] = $var;
            $var['name'] = '{характеристики}';
            $var['note'] = 'характеристики товара в виде строки';
            $vars[] = $var;
            $var['name'] = '{артикул}';
            $var['note'] = 'артикул товара';
            $vars[] = $var;
            $var['name'] = '{краткое описание}';
            $var['note'] = 'краткое описание товара';
            $vars[] = $var;
            $var['name'] = '{описание товара}';
            $var['note'] = 'meta описание товара';
            $vars[] = $var;
            $var['name'] = '{ключевые слова}';
            $var['note'] = 'meta ключевые слова товара';
            $vars[] = $var;
            $var['name'] = '{название группы}';
            $var['note'] = 'наименование группы товара';
            $vars[] = $var;
            $var['name'] = '{единица измерения}';
            $var['note'] = 'единица измерения';
            $vars[] = $var;
        }
        if ($this->input["type"] == 'goodsGroups') {
            $var['name'] = '{name}';
            $var['note'] = 'название группы со склонением';
            $vars[] = $var;
            $var['name'] = '{asname}';
            $var['note'] = 'название группы без склонения';
            $vars[] = $var;
            $var['name'] = '{краткое описание}';
            $var['note'] = 'краткое описание';
            $vars[] = $var;
            $var['name'] = '{ключевые слова}';
            $var['note'] = 'meta ключевые слова группы';
            $vars[] = $var;
            $var['name'] = '{описание группы}';
            $var['note'] = 'описание группы';
            $vars[] = $var;
        }

        $u = new DB('shop_variables', 'sv');
        $u->orderBy('name');
        $objects = $u->getList();
        foreach ($objects as $item) {
            $var = $item;
            $var['name'] = '{' . $item['name'] . '}';
            $var['note'] = $item['value'];
            $var['isDynamic'] = true;
            $vars[] = $var;
        }
        $this->result['count'] = sizeof($vars);
        $this->result['items'] = $vars;

        return $vars;
    }

    protected function correctValuesBeforeSave()
    {
        $this->input["name"] = trim(trim($this->input["name"], "}"), "{");
    }
}