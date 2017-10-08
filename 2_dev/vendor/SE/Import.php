<?php

namespace SE;
// завод // автопогрузчик
use \PHPExcel_IOFactory as PHPExcel_IOFactory;
use \PHPExcel_Autoloader as PHPExcel_Autoloader;

// отладка
function debugging($group,$funct,$act) {    // группа_логов/функция/комент

    // значение:  True/False (печатать/не_печатать в логи)
    $print = array(
        'funct'                 => False,       // безымянные
        'передача продукта'     => False,
        'name => id'            => False,
        'img'                   => False
    );

    if($print[$group] == True) {
        $wrLog          = __FILE__;
        $Indentation    = str_repeat(" ", (100 - strlen($wrLog)));
        $wrLog          = "{$wrLog} {$Indentation}| Start function: {$funct}";
        $Indentation    = str_repeat(" ", (150 - strlen($wrLog)));
        writeLog("{$wrLog}{$Indentation} | Act: {$act}");
    }

}

class Import {

    private $data = array();

    public $fieldsMap = array();

    // поля в эксель
    public $fields = array(
        // смотреть в БД
        "id"                => "Ид.",
        "article"           => "Артикул",
        "code"              => "Код (URL)",             // "Код (URL)" ??

        "id_group"          => "Ид. категории",
        "code_group"        => "Код категории",
        "path_group"        => "Путь категории",

        "id_brand"          => "Бренд",                 // проходит через конвертер имя -> id

        "name"              => "Наименование",
        "last_name"         => "Фамилия",
        "first_name"        => "Имя",
        "sec_name"          => "Отчество",

        "price"             => "Цена пр.",
        "price_opt"         => "Цена опт.",
        "price_opt_corp"    => "Цена корп.",
        "price_purchase"    => "Цена закуп.",
        "bonus"             => "Цена бал.",

        "presence_count"    => "Остаток",
        "step_count"        => "Шаг количества",

        //"category"          => "Категория",

        "weight"            => "Вес",
        "volume"            => "Объем",
        "measures_weight"   => "Меры веса",
        "measures_volume"   => "Меры объема",


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
        "is_market"         => "Маркет",

        "img_alt"           => "Изображения",
        // "description"       => "Характеристики",
        'min_count'         => "Мин.кол-во",

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

    // конвертация в JS
    public function convJS($nm){
        debugging('funct',__FUNCTION__.' '.__LINE__); // отладка

        $letters = array(
            '_a' => 'A', '_b' => 'B', '_c' => 'C', '_d' => 'D',
            '_e' => 'E', '_f' => 'F', '_g' => 'G', '_h' => 'H',
            '_i' => 'I', '_j' => 'J', '_k' => 'K', '_l' => 'L',
            '_m' => 'M', '_n' => 'N', '_o' => 'O', '_p' => 'P',
            '_q' => 'Q', '_r' => 'R', '_s' => 'S', '_t' => 'T',
            '_u' => 'U', '_v' => 'V', '_w' => 'W', '_x' => 'X',
            '_y' => 'Y', '_z' => 'Z'
        );

        foreach($letters as $key => $value)
            $nm = str_replace($key, $value,  $nm);
        return $nm;
    }

    // сборка
    public function __construct($settings = null)
    {
        debugging('funct',__FUNCTION__.' '.__LINE__); // отладка

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
        debugging('funct',__FUNCTION__.' '.__LINE__); // отладка

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
    // добавляется обработка массива
    private function addCategory($idCat,$idURL, $idWay){
        debugging('funct',__FUNCTION__.' '.__LINE__); // отладка

        if(!$this->check($idCat) and !empty($idCat)){
            $ways = (array) explode('/',$idWay);
            $newCat = array(
                'id'        => $idCat[0],
                'code_gr'   => $idURL[0],
                'name'      => array_pop($ways[0])
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
        debugging('funct',__FUNCTION__.' '.__LINE__); // отладка
        foreach ($this->importData[$type] as $cat)
            if ($cat['id'] == $id) return TRUE;
        return FALSE;
    }

    // Получить данные из файла
    public function getDataFromFile($filename){
        debugging('funct',__FUNCTION__.' '.__LINE__); // отладка
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
        debugging('funct',__FUNCTION__.' '.__LINE__); // отладка

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
        debugging('funct',__FUNCTION__.' '.__LINE__); // отладка
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

    // Получение ИД от Имени/Кода... (поддерживает переменные и списки,когда в ячейке эксель несколько значений)
    private function getId($key = 'id', $delimiter, $reconTable, $column, $item){ // ключ / разделитель / таблСверки / значение
        debugging('funct',__FUNCTION__.' '.__LINE__); // отладка

        if(isset($item[$this->fieldsMap[$key]]) and !empty($item[$this->fieldsMap[$key]])) {
            //writeLog(iconv('utf-8', 'windows-1251', $item[$this->fieldsMap[$key]]));

            // разбиение переменной на список
            $listObj = $item[$this->fieldsMap[$key]];
            $listObj = explode($delimiter, $listObj);

            // получение данных из таблицы
            $objects = NULL;
            $u = new DB($reconTable, $reconTable);
            $u->select($reconTable.'.id Id, '.$reconTable.'.'.$column);
            $u->groupBy($reconTable.'.id');
            $u->orderBy($reconTable.'.id');
            $objects = $u->getList();
            unset($u); // удаление переменной

            // сопоставление вход_списка с данными из таблицы
            $get = array();
            foreach ($listObj as $lObj) {
                $code = NULL;
                foreach ($objects as $object) {
                    debugging('name => id', __FUNCTION__ . ' ' . __LINE__, $object[id] . ' ' . $object[$this->convJS($column)]); // отладка

                    // trim: удаление пробелов в начале и конце строки,
                    // mb_strtolower: к нижнему регистру
                    $lObj = mb_strtolower(trim($lObj));
                    $obj = mb_strtolower(trim($object[$this->convJS($column)]));

                    if ($lObj == $obj) {
                        $code = (int)$object[id];
                    }
                }
                array_push($get, $code);
            }

            if (count($get) == 1)
                $get = $get[0];

            return $get;

        } else
            return NULL;
    }


    // получить
    // ПЕРЕДЕЛЫВАТЬ ПОД МУЛЬТИ ПАРАМЕТРЫ!!! id_group code_group
    private function get( $key = 'id', $item){
        debugging('funct', __FUNCTION__ . ' ' . __LINE__); // отладка

        // разложение строки на элементы
        if(gettype($item[$this->fieldsMap[$key]]) == string)
            $fieldsM = explode(",", $item[$this->fieldsMap[$key]]);
        else
            $fieldsM = array((string) $item[$this->fieldsMap[$key]]);

        $itemFinish = array();
        foreach ($fieldsM as $fM) {
            if (isset($fM) and !empty($fM))
                array_push($itemFinish, $fM); // добавить элемент в масив
        }

        if(count($itemFinish) == 0)     return NULL;            // если item-ов 0 - передаем NULL
        elseif(count($itemFinish) == 1) return $itemFinish[0];  // один - вытаскиваем его из массива и передаем
        else                            return $itemFinish;     // в ином случае передаем массив item-ов
    }

    // Получить правильные данные
    private function getRightData($item){
        debugging('funct',__FUNCTION__.' '.__LINE__); // отладка
        // $item - данные по КАЖДОМУ товару

        // Добавляем категории
        $this->addCategory(
            $this->get('id_group',$item),
            $this->get('code_group',$item),
            $this->get('path_group',$item)
        );

        // привязка товара к группе по id_group или code_group
        if($this->get('id_group',$item) == NULL)
            $id_gr = $this->getId('code_group', ",", 'shop_group', 'code_gr', $item);
        else
            $id_gr = $this->get('id_group',$item);

        // Добавляем меры (веса/объема)
        $this->importData['measure'][] = array(
            'id_price' =>           $this->get('id',$item),
            "id_weight_view" =>     $this->getId('measures_weight', ",", 'shop_measure_weight', 'name', $item)[0], // НЕ ПРЕВОДИТЬ В int >> не отфильтровывается значеине при передаче
            "id_weight_edit" =>     $this->getId('measures_weight', ",", 'shop_measure_weight', 'name', $item)[1],
            "id_volume_view" =>     $this->getId('measures_volume', ",", 'shop_measure_volume', 'name', $item)[0],
            "id_volume_edit" =>     $this->getId('measures_volume', ",", 'shop_measure_volume', 'name', $item)[1]
        );

        // ас.массив значений записи в БД
        $Product0 = array(
            'id' =>                 $this->get('id',$item),
            'id_group' =>           $id_gr,
            'name' =>               $this->get('name',$item),
            'article' =>            $this->get('article',$item),
            'code' =>               $this->get('code',$item),
            'price' =>              (int) $this->get('price',$item),
            'price_opt' =>          (int) $this->get('price_opt',$item),
            'price_opt_corp' =>     (int) $this->get('price_opt_corp',$item),
            'price_purchase' =>     (int) $this->get('price_purchase',$item),
            'bonus' =>     			(int) $this->get('bonus',$item),
            'presence_count' =>     $this->get('presence_count',$item),
            'presence' =>           NULL, // если Остаток текстовый - поле заполняется ниже
            'step_count' =>         $this->get('step_count',$item),

            'weight' =>             $this->get('weight',$item),
            'volume' =>             $this->get('volume',$item),

            'measure' =>            $this->get('measure',$item),
            'note' =>               $this->get('note',$item),
            'text' =>               $this->get('text',$item),
            'curr' =>               $this->get('curr',$item),

            'enabled' =>            $this->get('enabled',$item),//
            'flag_new' =>           $this->get('flag_new',$item),
            'flag_hit' =>           $this->get('flag_hit',$item),
            'is_market' =>          (int) $this->get('is_market',$item),

            "img_alt" =>            $this->get('img_alt',$item),
            // "description" =>        $this->get('description',$item),
            'min_count' =>          (int) $this->get('min_count',$item),
            'id_brand' =>           $this->getId('id_brand', ",", 'shop_brand', 'name', $item)
            // смотреть в БД

        );

        // обработчик значений/текста в Остатке
        if((int)$Product0['presence_count'] == 0) {
            $Product0['presence'] = $Product0['presence_count'];
            $Product0['presence_count'] = -1;
        }

        // фильтрация пустых полей в продукте
        $Product = array();
        foreach ($Product0 as $ingredient=>$include) {
            if($include !== NULL) {$Product[$ingredient]= $include;};
        };

        // получение списка изображений из ячеек Excel
        $imgList = array('img_alt','img', 'img_2', 'img_3', 'img_4', 'img_5', 'img_6', 'img_7', 'img_8', 'img_9', 'img_10');

        foreach ($imgList as $imgKey){
            if($imgLL = $this->get($imgKey,$item)){
                $imgLL = iconv('utf-8', 'windows-1251', $imgLL);
                $imgLL = explode(",", $imgLL);

                // преобразование информации по изображения под таблицу shop_img
                foreach ($imgLL as $result) {
                    debugging('img', __FUNCTION__ . ' ' . __LINE__, $result); // отладка

                    if ($result != '') {
                        $newImg = array("id_price" => $this->get('id', $item), "picture" => $result);

                        // главное изображение
                        if (($result == $imgLL[0] and $imgKey == 'img_alt') or $imgKey == 'img') {
                            $newImg["default"] = 1;
                        } else $newImg["default"] = 0;
                        $this->importData['img'][] = $newImg; // возможно это нужно вынести за скобки
                    }
                }
            }
        }

        $this->importData['products'][] = $Product;
    }

    // Добавить данные
    private function addData(){
        debugging('funct',__FUNCTION__.' '.__LINE__); // отладка
        try{
            //writeLog($this->mode,'MODE');
            $param = true;
            // удалить все строки в таблицах БД...
            if ($this->mode == 'rld') {
                DB::query("SET foreign_key_checks = 0");
                DB::query("TRUNCATE TABLE shop_group");
                DB::query("TRUNCATE TABLE shop_price");
//                    DB::query("TRUNCATE TABLE shop_brand");
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
            if (!empty($this->importData['category'])){
                DB::query("SET foreign_key_checks = 0");
                DB::insertList('shop_group', $this->importData['category'],TRUE);
                DB::query("SET foreign_key_checks = 1");
            }

            // импорт мер (веса/объема)
            if (!empty($this->importData['measure'])){
                DB::query("SET foreign_key_checks = 0");
                DB::insertList('shop_price_measure', $this->importData['measure'],TRUE);
                DB::query("SET foreign_key_checks = 1");
            }

            // импорт товаров
            if (!empty($this->importData['products'])) {
                // $this->mode == 'upd' отвечает за обновление (доавление к существующим, например, товарам)
                if ($this->mode == 'upd'){
                    $this->updateList();
                } else {

                    // передать импортируемые данные в БД таблица: 'shop_price'
                    debugging('передача продукта',__FUNCTION__.' '.__LINE__,'передать импортируемые данные в БД таблица: "shop_price"'); // отладка
                    // writeLog($this->importData['products']); // прослушивание передачи продукта

                    // если id_group приходит массивом - вытаскиваем первый (главный) элемент
                    $data = array();
                    foreach ($this->importData['products'] as &$prdct) {
                        $d = $prdct;
                        if(gettype($prdct['id_group']) == 'array')
                            $d['id_group'] = $d['id_group'][0];
                        array_push($data, $d);
                    }

                    DB::insertList('shop_price', $data,$param);

                    DB::query("SET foreign_key_checks = 0");

                    $data = array();
                    foreach ($this->importData['products'] as &$prdct){

                        // если значение одно - завернуть в массив для обработки
                        if(gettype($prdct['id_group']) == string)
                            $prdct['id_group'] == array($prdct['id_group']);

                        foreach ($prdct['id_group'] as $i)
                            $this->deleteCategory($prdct['id'], $i);

                        // получение первого элемента массива с присваиванием is_main равным 1 (главная группа)
                        if(isset($prdct['id'],$prdct['id_group'][0])) {
                            array_push($data,
                                array(
                                    'id_price' => (int) $prdct['id'],
                                    'id_group' => (int) $prdct['id_group'][0],
                                    'is_main'  => (bool) 1
                                    // добавить code_group?
                                )
                            );
                        };

                        // получает из массива следующие после первого элементы
                        // с присваиванием is_main равным 0 (второстепенные группы)
                        foreach ($prdct['id_group'] as $i) {
                            if(isset($prdct['id'],$i)) {
                                if ($i != $prdct['id_group'][0]) {
                                    array_push($data,
                                        array(
                                            'id_price' => (int) $prdct['id'],
                                            'id_group' => (int) $i,
                                            'is_main'  => (bool) 0
                                        )
                                    );
                                }
                            }
                        };
                    }
                    DB::insertList('shop_price_group', $data);
                }
                DB::query("SET foreign_key_checks = 1");
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
        debugging('funct',__FUNCTION__.' '.__LINE__); // отладка
        $spg = new DB('shop_price_group','spg');
        $spg->select('spg.*');
        $spg->where('id_price = ?', $id_price);
        $spg->andWhere('id_group = ?', $id_group);
        $spg->deleteList();
    }

    // Список обновлений (добавление к существующим, например, товарам)
    public function updateList()
    {
        debugging('funct',__FUNCTION__.' '.__LINE__); // отладка
        $products = $this->importData['products'];
        $pr = new DB('shop_price');
        $spg = new DB('shop_price_group','spg');
        try {
            $categoryList = array();
            // по каждому продукту
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

    // обновить группы таблиц
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
        //writeLog($data);
        DB::insertList('shop_group_tree', $data);

    }

    // добавить в дерево
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
