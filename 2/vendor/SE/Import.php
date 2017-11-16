<?php

namespace SE;
// завод // автопогрузчик
use \PHPExcel_IOFactory as PHPExcel_IOFactory;
use \PHPExcel_Autoloader as PHPExcel_Autoloader;


// отладка
function debugging($group,$funct,$act) {    // группа логв / функция / комент
    // значение: 1 - печатать в логи / 0 - не печатать
    $print = array(
        'funct'                 => 0,       // безымянные
        'передача продукта'     => 1
    );

    if($print[$group] == 1) {
        $wrLog = "Import.php";
        $Indentation = str_repeat(" ", (20 - strlen($wrLog)));
        $wrLog = "{$wrLog} {$Indentation}| Start function: {$funct}";
        $Indentation = str_repeat(" ", (70 - strlen($wrLog)));
        writeLog("{$wrLog}{$Indentation} | Act: {$act}");
    }
}

class Import {

    private $data = array();

    public $fieldsMap = array();

    // поля в эксель
    public $fields = array(
        "id"                => "Ид.",
        "article"           => "Артикул",
        "code"              => "Код (URL)", // "Код (URL)" ??
        "id_group"          => "Ид. категории",
        "code_group"        => "Код категории",
        "path_group"        => "Путь категории",
        "name"              => "Наименование",
        "last_name"         => "Фамилия",
        "first_name"        => "Имя",
        "sec_name"          => "Отчество",
        "price"             => "Цена",
        "price_opt"         => "Цена опт.",
        "price_opt_corp"    => "Цена корп.",
        "price_purchase"    => "Цена закуп.",
        "presence_count"    => "Кол-во",
        "brand"             => "Бренд",
        "weight"            => "Вес",
        "volume"            => "Объем",
        "measure"           => "Ед.Изм.",
        "note"              => "Краткое описание",
        "text"              => "Полное описание",
        "curr"              => "КодВалюты",

        //"title"             => "Тег title",
        "title"             => "MetaHeader",
        //"title"             => "Мета: загаловок",

        "keywords"          => "MetaKeywords",
        //"keywords"          => "Мета: ключевые слова",

        "description"       => "MetaDescription",
        //"description"       => "Мета: описание",

        "enabled"           => "Видимость",
        "flag_new"          => "Новинки",
        "flag_hit"          => "Хиты",
        "img_alt"           => "Изображения",
        // "description"       => "Характеристики",
        'min_count'         => "Мин.кол-во",


        // смотреть в БД
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

    // сборка
    public function __construct($settings = null)
    {
        debugging('funct','__construct'); // отладка
        if($settings['type'] == 0)
            $this->mode = 'upd';
        else if($settings['reset'])
            $this->mode = 'rld';
        else
            $this->mode = 'ins';

        if($settings['prepare'] == "true") $this->mode = 'pre';
    }

    // запуск импорта
    public function startImport($filename, $prepare = false){
        debugging('funct','startImport'); // отладка

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

    // добавить категорию
    private function addCategory($idCat,$idURL, $idWay){
        debugging('funct','addCategory'); // отладка
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
    // проверить
    private function check($id, $type = 'category'){
        debugging('funct','check'); // отладка
        foreach ($this->importData[$type] as $cat)
            if ($cat['id'] == $id) return TRUE;
        return FALSE;
    }

    // Получить данные из файла
    public function getDataFromFile($filename){
        debugging('funct','getDataFromFile'); // отладка
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
                writeLog('ФАЙЛА НЕТ '. $file);
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
        //debugging('funct','prepareData'); // отладка
        $array = array_shift($this->data);
        foreach ($this->fields as $name => $rus){
            foreach ($array as $key => $field) {
                if($field == $rus){
                    $this->fieldsMap[$name] = $key;
                }
            }
        }
    }

    // Данные итератора
    private function iteratorData($prepare = false){
        //debugging('funct','iteratorData'); // отладка
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

    // получить
    private function get( $key = 'id', $item){
       // debugging('funct','get'); // отладка
        if(isset($item[$this->fieldsMap[$key]]) and !empty($item[$this->fieldsMap[$key]]))
            return $item[$this->fieldsMap[$key]];
        else
            return NULL;
    }

    // Получить правильные данные
    private function getRightData($item){
        //debugging('funct','getRightData'); // отладка

        // Добавляем категории
        $this->addCategory(
            $this->get('id_group',$item),
            $this->get('code_group',$item),
            $this->get('path_group',$item)
        );

        writeLog($item);

        // продукт
        $Product0 = array(
            'id' =>                 $this->get('id',$item),
            'id_group' =>           $this->get('id_group',$item),
            'name' =>               $this->get('name',$item),
            'article' =>            $this->get('article',$item),
            'code' =>               $this->get('code', $item),
            'price' =>              $this->get('price',$item),
            'price_opt' =>          $this->get('price_opt',$item),
            'price_opt_corp' =>     $this->get('price_opt_corp',$item),
            'price_purchase' =>     $this->get('price_purchase',$item),
            'presence_count' =>     $this->get('presence_count',$item),
            'weight' =>             $this->get('weight',$item),
            'volume' =>             $this->get('volume',$item),
            'measure' =>             $this->get('measure',$item),
            'note' =>               $this->get('note',$item),
            'text' =>               $this->get('text',$item),
            'curr' =>               $this->get('curr',$item),
            'enabled' =>            $this->get('enabled',$item),
            'flag_new' =>           $this->get('flag_new',$item),
            'flag_hit' =>           $this->get('flag_hit',$item),
            "img_alt" =>            $this->get('img_alt',$item),
            // "description" =>        $this->get('description',$item),
            'min_count' =>          (int) $this->get('min_count',$item),
            // смотреть в БД

        );

        // фильтрация пустых полей в продукте
        $Product = array();
        foreach ($Product0 as $ingredient=>$include) {
            if($include !== NULL) {$Product[$ingredient]= $include;};
        };

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

    // Добавить данные
    private function addData(){
        //debugging('funct','addData'); // отладка
        try{
            //writeLog($this->mode,'MODE');
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
            } else {
                $param = false;
            }
            // импорт категорий
            //writelog($this->importData);
            if (!empty($this->importData['category'])){
                DB::query("SET foreign_key_checks = 0");
               // writeLog($this->importData['category']);
                DB::insertList('shop_group', $this->importData['category'],TRUE);
                DB::query("SET foreign_key_checks = 1");
            }
            if (!empty($this->importData['products'])) {
                if ($this->mode == 'upd'){
                    $this->updateList();
                } else {

                    // передать импортируемые данные в БД таблица: 'shop_price'
                    debugging('передача продукта','addData','передать импортируемые данные в БД таблица: "shop_price" ~314'); // отладка
                    // writeLog($this->importData['products']); // прослушивание передачи продукта
                    DB::insertList('shop_price', $this->importData['products'],$param);
                    writeLog($this->importData['products']);

                    DB::query("SET foreign_key_checks = 0");
                    foreach ($this->importData['products'] as &$prdct){
                        if(isset($prdct['id'],$prdct['id_group'])){
                            $this->deleteCategory($prdct['id'],$prdct['id_group']);

                            $data = array(
                                array(
                                    'id_price' => $prdct['id'],
                                    'id_group' => $prdct['id_group'],
                                    'is_main'  => 1
                                )
                            );
                            //writeLog($data);
                            DB::insertList('shop_price_group', $data);
                        }
                    }
                   DB::query("SET foreign_key_checks = 1");
                }
            }
            // импорт изображений
            if (!empty($this->importData['img'])){
                DB::query("SET foreign_key_checks = 0");
                DB::insertList('shop_img', $this->importData['img'], TRUE);
                DB::query("SET foreign_key_checks = 1");
            }
            $this->updateGroupTable();
            $this->importData = null;
        } catch (Exception $e){
            writeLog($e->getMessage());
            //DB::rollBack();
            return FALSE;
        }
        return true;
    }

    // удалить категорию
    private function deleteCategory($id_price,$id_group){
        debugging('funct','deleteCategory'); // отладка
        $spg = new DB('shop_price_group','spg');
        $spg->select('spg.*');
        $spg->where('id_price = ?', $id_price);
        $spg->andWhere('id_group = ?', $id_group);
        $spg->deleteList();
    }

    // Список обновлений
    public function updateList()
    {
        debugging('funct','updateList'); // отладка
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

    public function updateGroupTable() {
        $sql = "CREATE TABLE IF NOT EXISTS shop_group_tree (
            id int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
            id_parent int(10) UNSIGNED NOT NULL,
            id_child int(10) UNSIGNED NOT NULL,
            level tinyint(4) NOT NULL,
            updated_at timestamp NOT NULL DEFAULT '0000-00-00 00:00:00' ON UPDATE CURRENT_TIMESTAMP,
            created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE INDEX UK_shop_group_tree (id_parent, id_child),
            CONSTRAINT FK_shop_group_tree_shop_group_id FOREIGN KEY (id_child)
            REFERENCES shop_group (id) ON DELETE CASCADE ON UPDATE CASCADE,
            CONSTRAINT FK_shop_group_tree_shop_group_tree_id_parent FOREIGN KEY (id_parent)
            REFERENCES shop_group (id) ON DELETE CASCADE ON UPDATE RESTRICT
            )
            ENGINE = INNODB
            CHARACTER SET utf8
            COLLATE utf8_general_ci;";

        DB::query($sql);

        $tree = array();

        $tbl = new DB('shop_group', 'sg');
        $tbl->select('upid, id');
        $list = $tbl->getList();
        foreach($list as $it){
            $tree[intval($it['upid'])][] = $it['id'];
        }


        unset($list);
        $data = $this->addInTree($tree);
        DB::query("TRUNCATE TABLE `shop_group_tree`");
        writeLog($data);
        DB::insertList('shop_group_tree', $data);

    }

    private function addInTree($tree , $parent = 0, $level = 0, &$treepath = array()){
        if ($level == 0) {
            $treepath = array();
        } else
            $treepath[$level] = $parent;

        foreach($tree[$parent] as $id) {
            $data[] = array('id_parent'=>$id, 'id_child'=>$id, 'level'=>$level);
            if ($level > 0)
                for ($l=1; $l <= $level; $l++){
                    $data[] = array('id_parent'=>$treepath[$l], 'id_child'=>$id, 'level'=>$level);
                }
            if (!empty($tree[$id])) {
                $data = array_merge ($data, $this->addInTree($tree , $id, $level + 1, $treepath));
            }
        }
        return $data;
    }
}
