<?php

namespace SE;

use \PHPExcel_IOFactory as PHPExcel_IOFactory;
use \PHPExcel_Autoloader as PHPExcel_Autoloader;

class Import {

    private $data = array();

    public $fieldsMap = array();

    public $fields = array(
        "id"                => "Ид.",
        "article"           => "Артикул",
        "code"              => "Код (URL)",
        "id_group"          => "Ид. категории",
        "code_group"        => "Код категории",
        "path_group"        => "Путь категории",
        "name"              => "Наименование",
        "price"             => "Цена пр.",
        "price_opt"         => "Цена опт.",
        "price_opt_corp"    => "Цена корп.",
        "price_purchase"    => "Цена закуп.",
        "presence_count"    => "Остаток",
        "brand"             => "Бренд",
        "weight"            => "Вес",
        "volume"            => "Объем",
        "measure"           => "Ед.Изм",
        "note"              => "Краткое описание",
        "text"              => "Полное описание",
        "curr"              => "Код валюты",
        "title"             => "Тег title",
        "keywords"          => "Мета-тег keywords",
        "description"       => "Мета-тег description",

        "img"               => "Фото 1",
        "img_2"             => "Фото 2",
        "img_3"             => "Фото 3",
        "img_4"             => "Фото 4",
        "img_5"             => "Фото 5",
        "img_6"             => "Фото 6",
        "img_7"             => "Фото 7",
        "img_8"             => "Фото 8",
        "img_9"             => "Фото 9",
        "img_10"            => "Фото 10"
    );

    public $importData = array(
        # shop_price
        #'products' => array(),
        # shop_group
        #'category' => array()
    );

    /**
     * ins  - Добавление в БД данных как новые строки
     * rld  - Добавление в БД данных как новые строки, с удалением других товаров
     * upd  - Обновление БД
     * pre  - Подготовка данных
     */
    public $mode = 'ins';

    public function __construct($settings = null)
    {
        if($settings['type'] == 0)
            $this->mode = 'upd';
        else if($settings['reset'])
            $this->mode = 'rld';
        else
            $this->mode = 'ins';

        if($settings['prepare'] == "true") $this->mode = 'pre';
    }

    public function startImport($filename, $prepare = false){
        if($this->getDataFromFile($filename)){
            if (!$prepare)
                $this->prepareData();
            $this->iteratorData($prepare);
            if ($prepare) {
                return $this->importData;
            } else
                $this->addData();
            return TRUE;
        }
        return FALSE;
    }

    private function addCategory($idCat,$idURL, $idWay){
        if(!$this->check($idCat) and !empty($idCat)){
            $ways = (array) explode('/',$idWay);
            $newCat = array(
                'id'        => $idCat,
                'code_gr'   => $idURL,
                'name'      => array_pop($ways)
            );
            /*if(count($ways) > 1 )
                $newCat['parent'] = array_pop($ways);
            else
                $newCat['parent'] = $ways[0];*/
            $this->importData['category'][] = $newCat;
        }
    }
    private function check($id, $type = 'category'){
        foreach ($this->importData[$type] as $cat)
            if ($cat['id'] == $id) return TRUE;
        return FALSE;
    }

    public function getDataFromFile($filename){
        try{
            $file = DOCUMENT_ROOT . "/files/".$filename;
            if(file_exists($file) and is_readable($file)){
                if(class_exists('PHPExcel_IOFactory',true)){
                    PHPExcel_Autoloader::Load('PHPExcel_IOFactory');
                    $obj_reader = PHPExcel_IOFactory::createReader('Excel2007');
                    $obj_reader->setReadDataOnly(true);
                    $objPHPExcel = $obj_reader->load($file);
                    $this->data = $objPHPExcel->setActiveSheetIndex(0)->toArray();
                    if(count($this->data) < 2){
                        return false;
                    }
                    return true;
                }
            } else {
                writeLog('ФАЙЛА НЕТУ '. $file);
            }
        } catch (Exception $e) {
            writeLog($e->getMessage());
        }
        return true;
    }

    /**
     * Готовим данные
     */
    private function prepareData(){
        $array = array_shift($this->data);
        foreach ($this->fields as $name => $rus){
            foreach ($array as $key => $field) {
                if($field == $rus){
                    $this->fieldsMap[$name] = $key;
                }
            }
        }
    }

    private function iteratorData($prepare = false){
        if($prepare)
            $i=0;
        foreach ($this->data as $key =>  $item){
            if($prepare){
                $this->importData['prepare'][$i] = $item;
                $i++;
                if($i > 1){
                    $this->importData['prepare'][2] = array_flip($this->fields);
                    break;
                }
            } else {
                $this->getRightData($item);
                $this->data[$key] = null;
            }
        }
    }

    private function get( $key = 'id', $item){
        if(isset($item[$this->fieldsMap[$key]]) and !empty($item[$this->fieldsMap[$key]]))
            return $item[$this->fieldsMap[$key]];
        else
            return NULL;
    }

    private function getRightData($item){

        # Добавляем категории
        $this->addCategory(
            $this->get('id_group',$item),
            $this->get('code_group',$item),
            $this->get('path_group',$item)
        );

        $Product = array(
            'id' =>                 $this->get('id',$item),
            'id_group' =>           $this->get('id_group',$item),
            'name' =>               $this->get('name',$item),
            'code' =>               $this->get('code',$item),
            'price' =>              $this->get('price',$item),
            'price_opt' =>          $this->get('price_opt',$item),
            'price_opt_corp' =>     $this->get('price_opt_corp',$item),
            'price_purchase' =>     $this->get('price_purchase',$item),
            'presence_count' =>     $this->get('presence_count',$item),
            'weight' =>             $this->get('weight',$item),
            'volume' =>             $this->get('volume',$item),
            'note' =>               $this->get('note',$item),
            'text' =>               $this->get('text',$item),
            'curr' =>               $this->get('curr',$item)
        );

        $imgList = array('img', 'img_2', 'img_3', 'img_4', 'img_5', 'img_6', 'img_7', 'img_8', 'img_9', 'img_10');

        foreach ($imgList as $imgKey){
            if($result = $this->get($imgKey,$item)){
                $newImg = array("id_price" => $this->get('id',$item), "picture" => $result);
                if($imgKey == 'img')
                   $newImg["default"] = 1;
                $this->importData['img'][] = $newImg;
            }
        }
        $this->importData['products'][] = $Product;
    }

    private function addData(){
        try{
            writeLog($this->mode,'MODE');
            if (!empty($this->importData['products'])) {
                $param = true;
                if ($this->mode == 'rld') {
                    DB::query("SET foreign_key_checks = 0");
                    DB::query("TRUNCATE TABLE shop_group");
                    DB::query("TRUNCATE TABLE shop_price");
                    DB::query("TRUNCATE TABLE shop_img");
                    DB::query("TRUNCATE TABLE shop_group_price");
                    DB::query("TRUNCATE TABLE shop_discounts");
                    DB::query("TRUNCATE TABLE shop_discount_links");
                    DB::query("TRUNCATE TABLE shop_modifications");
                    DB::query("TRUNCATE TABLE shop_modifications_group");
                    DB::query("TRUNCATE TABLE shop_feature_group");
                    DB::query("TRUNCATE TABLE shop_feature");
                    DB::query("TRUNCATE TABLE shop_group_feature");
                    DB::query("TRUNCATE TABLE shop_modifications_feature");
                    DB::query("TRUNCATE TABLE shop_feature_value_list");
                    DB::query("TRUNCATE TABLE shop_modifications_img");
                    DB::query("TRUNCATE TABLE shop_tovarorder");
                    DB::query("TRUNCATE TABLE shop_order");
                    DB::query("SET foreign_key_checks = 1");
                } else $param = false;

                if ($this->mode == 'upd'){
                    $this->updateList();
                } else {
                    DB::insertList('shop_price', $this->importData['products'],$param);
                    foreach ($this->importData['products'] as &$prdct){
                        if(isset($prdct['id'],$prdct['id_group'])){
                            $this->deleteCategory($prdct['id'],$prdct['id_group']);
                            DB::insertList('shop_price_group',array(
                                array(
                                    'id_price' => $prdct['id'],
                                    'id_group' => $prdct['id_group'],
                                    'is_main'  => 1
                                )
                            ));
                        }
                    }
                }
            }
            if (!empty($this->importData['img'])){
                DB::query("SET foreign_key_checks = 0");
                DB::insertList('shop_img', $this->importData['img'], TRUE);
                DB::query("SET foreign_key_checks = 1");
            }
            if (!empty($this->importData['category'])){
                DB::query("SET foreign_key_checks = 0");
                DB::insertList('shop_group', $this->importData['category'],TRUE);
                DB::query("SET foreign_key_checks = 1");
            }
            $this->importData = null;
        } catch (Exception $e){
            writeLog($e->getMessage());
            DB::rollBack();
            return FALSE;
        }
        return true;
    }

    private function deleteCategory($id_price,$id_group){
        $spg = new DB('shop_price_group','spg');
        $spg->select('spg.*');
        $spg->where('id_price = ?', $id_price);
        $spg->andWhere('id_group = ?', $id_group);
        $spg->deleteList();
    }

    public function updateList()
    {
        $products = $this->importData['products'];
        $pr = new DB('shop_price');
        $spg = new DB('shop_price_group','spg');
        try {
            $categoryList = array();
            foreach ($products as &$item){

                DB::beginTransaction();
                $pr->setValuesFields($item);
                $result = $pr->save();

                if(CORE_VERSION == "5.3" and is_numeric($result) and isset($item['id_group'])){
                    // Удаляем старые записи
                    $spg->select('spg.*');
                    $spg->where('id_price = ?', $result);
                    $spg->andWhere('id_group = ?', $item['id_group']);
                    $spg->deleteList();
                    // Добавляем новые
                    $categoryList[] =array(
                        'id_price' => $result,
                        'id_group' => $item['id_group'],
                        'is_main'  => 1
                    );
                }
                DB::commit();
            }
            DB::insertList('shop_price_group',$categoryList);
        } catch (Exception $e) {
            DB::rollBack();
            return false;
        }
        return true;
    }
}

