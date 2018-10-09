<?php

namespace SE\Shop;

require_once $_SERVER['DOCUMENT_ROOT'] . '/api/lib/PHPExcel/Classes/PHPExcel.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/api/lib/PHPExcel/Classes/PHPExcel/IOFactory.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/api/lib/PHPExcel/Classes/PHPExcel/Writer/Excel2007.php';

use \PHPExcel_IOFactory as PHPExcel_IOFactory;
use \PHPExcel_Autoloader as PHPExcel_Autoloader;
use SE\Shop\Product;
use SE\DB;
use SE\Shop\ReadFilter;
use \PDO as PDO;
use SE\Exception;


class Import extends Product
{

    private $data = array();
    private $cycleNum = 0;
    private $checkGroupIdName = FALSE;     /** проверка наличия массива ид-имя группы в сессии */
    private $feature = array();            /** @var array характеристики из БД */
    private $modData = array();            /** @var array данные для заполнения модификации в таблицАХ */
    private $thereModification = array();  /** @var array вкл/выкл создания характеристик */
    private $productTables = array();      /** @var array таблицы по товару (временные данные) */

    public $fieldsMap = array();

    /** поля в эксель */
    public $fields = array(
        /** смотреть в БД */
        "id"                => "Ид.",
        "article"           => "Артикул",
        "code"              => "Код (URL)",             // "Код (URL)" ??

        "id_group"          => "Ид. категории",
        "code_group"        => "Код категории",
        "path_group"        => "Путь категории",

        "id_brand"          => "Бренд",                 /** проходит через конвертер имя -> id */

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
        "features"          => "Характеристики",
        'min_count'         => "Мин.кол-во",
        'code_acc'          => "Сопутствующие товары",

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
     * ins Добавление в БД данных как новые строки
     * rld Добавление в БД данных как новые строки, с удалением других товаров
     * upd Обновление БД
     * pre Подготовка данных
     */
    public $mode = 'ins';

    public function convJS($nm)
    {
        /** Конвертация в JS */
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');

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
    } // Конвертация в JS

    public function __construct($settings = null, $options)
    {
        /** сборка */
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');

        if($settings['type'] == 0)
            $this->mode = 'upd';
        else if($settings['reset'])
            $this->mode = 'rld';
        else
            $this->mode = 'ins';

        if($settings['prepare'] == "true") $this->mode = 'pre';
    } // сборка

    public function startImport($filename, $prepare = false, $options, $customEdition, $cycleNum, $lastCycle)
    {
        /** Запуск импорта
         *
         * @@@@@@ @@@@@@@@    @@    @@@@@@ @@@@@@@@    @@@@@@ @@     @@ @@@@@@ @@@@@@ @@@@@@ @@@@@@@@
         * @@        @@      @@@@   @@  @@    @@         @@   @@@   @@@ @@  @@ @@  @@ @@  @@    @@
         * @@@@@@    @@     @@  @@  @@@@@@    @@         @@   @@ @@@ @@ @@@@@@ @@  @@ @@@@@@    @@
         *     @@    @@    @@@@@@@@ @@ @@     @@         @@   @@  @  @@ @@     @@  @@ @@ @@     @@
         * @@@@@@    @@    @@    @@ @@  @@    @@       @@@@@@ @@     @@ @@     @@@@@@ @@  @@    @@
         *
         * if     - превью      - чтение первых 1000 строк
         * elseif - первый цикл - весь файл во временные
         *
         * @param int   $_SESSION['lastProcessedLine'] последняя обработанная строка (без превью)
         * @param array $_SESSION['errors']            данные по некритич. ошибкам, возникшим при обработки импФайла
         * @method bool public  getDataFromFile($filename, $options, $prepare) чтение и разложение файла на временные
         * @method      private inserRelatedProducts()       запись сопутств. тов. (shop_accomp)
         * @method      public  rmdir_recursive(string $dir) очистка директ. с времен. файлами
         */
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');

        $this->cycleNum = $cycleNum;
        if ($prepare) {
            /** превью - обнуление переменных сессии, чтение файла */
            unset($_SESSION["cycleNum"]);
            unset($_SESSION["pages"]);
            $_SESSION['lastCodePrice'] = '';
            $_SESSION['lastIdPrice'] = '';
            $_SESSION["countPages"] = 0;
            unset($_SESSION["getId"]);
            /**
             * проверка:   +ид                           -путьКат(может быть любым)  -идКат(на импорт не влияет)
             *             -кодКат(на импорт не влияет)  +Код (URL)                  -артикул(может быть любым)
             *             +Цена пр.                     +Цена закуп.                +Цена опт.
             *             +Цена бал.                    -Остаток                    +Маркет
             *             +Изображения                  +Мин.кол-во
             *             +характ
             */
            $_SESSION['errors']            = array();
            $this->getDataFromFile($filename, $options, $prepare);
            $this->cycleNum                = 0;
            $_SESSION['skip']              = $options['skip'];
            $_SESSION['lastProcessedLine'] = 0;
        } elseif ($this->cycleNum == 0) {
            $_SESSION['errors']            = array();
            $_SESSION["cycleNum"] = 0;
            unset($_SESSION["pages"]);
            $this->getDataFromFile($filename, $options, $prepare);
        }
        if ($prepare or $this->cycleNum != 0) {
            $clearDB = $this->clearDB();
            if($clearDB==false) $this->getGroupsCodesIdsPath(); /** подгрузка кодов-ids, путей-ids категорий из БД */
            if($clearDB==false) $this->getNamesIds();           /** подгрузка характеристик и модификаций из БД */

            /** перебор записанных файлов */
            if ($prepare)       $this->data = $this->readTempFiles($this->cycleNum);
            else                $this->data = $this->readTempFiles($this->cycleNum-1);

            if ($prepare or $this->cycleNum == 1)  {
                $this->prepareData($customEdition, $options, $prepare); /** заголовки */
                $this->communications();                                /** связи */
            } elseif (!$prepare)  $this->fieldsMap = $_SESSION["fieldsMap"];

            $this->iteratorData($prepare, $options);

            if ($prepare) {
                unlink(DOCUMENT_ROOT . "/files/tempfiles/tempfile0.TMP");
                return $this->importData;
            } else
                $this->addData();

            /** последний цикл */
            if ($lastCycle) {
                $this->inserRelatedProducts();
                $tempfiles = DOCUMENT_ROOT . "/files/tempfiles";
                $this->rmdir_recursive($tempfiles);                  /** очистка директ. с времен. файлами */
                if (!file_exists($tempfiles) || !is_dir($tempfiles))
                    mkdir($tempfiles, 0766, true);  /** рекурс. создание директ. */
            }

            $_SESSION["cycleNum"] += 1;
            unset($_SESSION["getId"]);
            unset($this->data);
        }
        return 0;
    } // ЗАПУСК ИМПОРТА

    private function clearDB()
    {
        /** Очистить таблицу товаров и все дочерние. Удалить все строки в таблицах БД... */
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');

        try{
            /** 1 удалить все строки в таблицах БД... */
            if ($this->mode == 'rld' and $_SESSION["cycleNum"] == 1) {
                DB::query("SET foreign_key_checks = 0");
                DB::query("TRUNCATE TABLE shop_group");
                DB::query("TRUNCATE TABLE shop_price");
                DB::query("TRUNCATE TABLE shop_price_group");
                DB::query("TRUNCATE TABLE shop_price_measure");
                DB::query("TRUNCATE TABLE shop_accomp");
                //DB::query("TRUNCATE TABLE shop_brand");
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
                return true;

            } elseif ($_SESSION["cycleNum"] == 1)  return false;
            return false;

        } catch (Exception $e){
            writeLog($e->getMessage());
            //DB::rollBack();
            return FALSE;
        }

    } // Очистить таблицу товаров и все дочерние

    private function getGroupsCodesIdsPath()
    {
        /** получить данные коды-ids, пути-ids групп в сессию (для генирации кодов новых групп - предотвращения совпадений)
         * 1 запрос на получение данных
         * 2 обработка пути группы
         * 3 заполнение данных сессии
         */


        /** 1 запрос на получение данных */
        $this->debugging('funct', __FUNCTION__ . ' ' . __LINE__, __CLASS__, '[comment]');
        $u = new DB('shop_group', 'sg');
        $u->reConnection();  /** перезагрузка запроса */
        if (CORE_VERSION != "5.2") {
            $u->select('sg.id, sg.code_gr, sg.name endname, sgt.level,
                        GROUP_CONCAT(CONCAT_WS(\':\', sgtp.level, sgp.name) SEPARATOR \';\') name');
            $u->innerJoin("shop_group_tree sgt",  "sgt.id_child = sg.id AND sg.id <> sgt.id_parent
                                                   OR sgt.id_child = sg.id AND sgt.level = 0");
            $u->innerJoin("shop_group_tree sgtp", "sgtp.id_child = sgt.id_parent");
            $u->innerJoin("shop_group sgp",  "sgp.id = sgtp.id_child");
            $u->orderBy('sgt.level');
        } else {
            $u->select('sg.*');
            $u->orderBy('sg.id');
        }
        $u->groupBy('sg.id');
        $groups = $u->getList();
        unset($u);


        /** 2 обработка пути группы */
        foreach ($groups as $k => $i) {

            $path = '';
            $pathArray = array();
            $dataname = explode(";", $i['name']);

            foreach ($dataname as $k2 => $i2) {
                $ki = explode(":", $i2);
                $pathArray[$ki[0]] = $ki[1];
            }

            foreach (range(0, count($pathArray)-1, 1) as $number)
                $path .= $pathArray[$number]."/";

            /** подстановка окончания, а в родительской - удаление слеша */
            if ($i["level"] == 0)  $path  = substr($path, 0, -1); /** удаление последнего знака (в родительской группе) */
            else                   $path .= $i["endname"];

            unset($groups[$k]['level']);
            unset($groups[$k]['endname']);
            $groups[$k]['name'] = $path;
        }


        /** 3 заполнение данных сессии */
        foreach ($list as $k => $i) {
            $code_gr = $i['codeGr'];
            $_SESSION["getId"]['code_gr'][$code_gr] = $i['id'];
            $_SESSION["getId"]['path_group'][$code_gr] = $i['id'];
        }

    } // подгрузка кодов-ids, путей-ids категорий из БД

    private function getNamesIds()
    {
        /** Получить данные по характеристикам и модификациям, получить их значения
         * подгрузка связки имя-id из БД:     1 ... характеристик        2 ... значений характеристик
         *                                    3 ... групп модификаций    4 ... соотношений Характеристик и ГруппМодификаций
         */
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');

        $this->getFeatureInquiry("shop_feature","sf","sf.id, sf.name");
        $this->getFeatureInquiry("shop_feature_value_list","sfvl","sfvl.id, sfvl.value name");
        $this->getFeatureInquiry("shop_modifications_group","smg","smg.id, smg.name");
        $this->getFeatureInquiry("shop_group_feature","sgf","CONCAT_WS(':', sgf.id_group, sgf.id_feature) name, sgf.id");

    } // подгрузка связки имя-id характеристик и модификаций из БД

    private function getFeatureInquiry($table,$abbreviation,$select,$section)
    {
        /** обычный запрос характеристик, модификаций к БД  [smg]=>aray( [<name>]=><id> ) */
        $u = new DB($table, $abbreviation);
        $u->select($select);
        $list = $u->getList(); unset($u);
        foreach ($list as $k => $i) $this->feature[$abbreviation][$i['name']] = $i['id'];
    } // обычный запрос характеристик, модификаций к БД

    private function addCategoryMain($code_group, $path_group)
    {
        /** Добавить Категорию / массив категорий
         * @param $code_group нумерованны массив или строка (автопреобразуется в массив)
         * @param $path_group нумерованны массив или строка (автопреобразуется в массив)
         */
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');

        /** унификация (приводим к одному формату) */
        if(gettype($code_group) == string)  $code_group = array($code_group);
        if(gettype($path_group) == string)  $path_group = array($path_group);
        /** категории приходят массивом */
        if(count($code_group) > 0 or count($path_group) > 0) {
            $list_group = max(count($code_group),count($path_group)) - 1;
            /** перебираем пришедшие группы */
            foreach (range(0, $list_group) as $unit_group) {
                if (!$this->check($code_group[$unit_group], 'code_group') or
                    !$this->check($path_group[$unit_group], 'path_group')
                ) {
                    /** раскладываем путь, создаем группы по пути + код присваиваем ПОСЛЕДНЕЙ группе */
                    $ways = (array)explode('/', $path_group[$unit_group]);
                    $countWays = count($ways) - 1;
                    $list_code = array();
                    $list_code[$countWays] = $code_group;
                    /** проходим по пути проверяя наличие группы, при отсутствие - генерим, получаем id */
                    foreach (range(0, $countWays) as $number) {
                        $this->addCategoryParents($number, $ways, $list_code, $unit_group);
                    }
                }
            }
        }
    } // Добавить Категорию / массив категорий

    private function addCategoryParents($number, $ways, $code_group, $unit_group)
    {
        /** Добавить категорию  (с поддержкой родитель-групп)
         *
         *
         *    @@    @@@@@@  @@@@@@     @@@@@@    @@    @@@@@@@@    @@@@@@    @@    @@@@@@
         *   @@@@   @@   @@ @@   @@    @@       @@@@      @@       @@  @@   @@@@   @@  @@
         *  @@  @@  @@   @@ @@   @@    @@      @@  @@     @@       @@@@@@  @@  @@  @@@@@@
         * @@@@@@@@ @@   @@ @@   @@    @@     @@@@@@@@    @@       @@     @@@@@@@@ @@ @@
         * @@    @@ @@@@@@  @@@@@@     @@@@@@ @@    @@    @@       @@     @@    @@ @@  @@
         *
         *
         * @param int $number     - номер ранжирования по пути группы
         * @param array $ways     - нумерованный массив, групп из пути разбитый по слешам, нулевой - родительский
         * @param str $code_group - строка, код группы
         * @param int $unit_group - число, порядковое группы в пришедшем массиве
         *
         * 1 определяем потребность в создании группы
         * 2 определение родителя
         * 3 generete group data. принять хотя бы 1 из 2 параметров - остальные сгенерировать, если отсутствуют
         * 4 write DB shop_group, get id group, write id in session
         */
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');

        /**
         * 1 определяем потребность в создании группы
         * @var array $section_groups  массив путь на кирилице strtolower(str_ireplace(" ", "-", rus2translit(implode("--", $section_groups)))). Преобразование в "klassicheskie-kovry--kovry"
         */
        $ar_pop_wa      = $ways[$number];
        $section_groups = array_slice($ways, 0, $number+1);
        $path_new_group = implode("/", $section_groups); /** имя на кирилице */

        if (!$_SESSION["getId"]["path_group"][$path_new_group]) {

            /** 2 определение родителя */
            if($number != 0) {
                $ar_pop_wa_par = array_slice($ways, 0, $number);
                $ar_pop_wa_par = implode("/", $ar_pop_wa_par);
                if($_SESSION["getId"]["path_group"][$ar_pop_wa_par])
                    $pare      = $_SESSION["getId"]["path_group"][$ar_pop_wa_par];
                else $pare     = NULL;
                // else $this->addCategoryParents($number, $ways, $code_group, $unit_group)
            }


            /** 3 generete group data. принять хотя бы 1 из 2 параметров - остальные сгенерировать, если отсутствуют */
            $parent = NULL;
            $error  = False;
            if(!empty($ar_pop_wa)) {
                $name    = $ar_pop_wa;
                $parent  = $pare;
                $code_gr = $this->generationGroupCode($ar_pop_wa);
            } elseif(!empty($code_group[$unit_group])) {
                $name    = $code_group[$unit_group];
                $parent  = $pare;
                $code_gr = $this->generationGroupCode($ar_pop_wa);
            } else
                $error   = True;


            /** 4 write DB shop_group, get id group, write id in session */
            if($error != True) {

                $newCat = array(
                    'code_gr'   => $code_gr,
                    'name'      => $name,
                );
                if ($parent != NULL) $newCat['upid'] = $parent;

                if(!empty($id) or !empty($code_gr) or !empty($name)) {
                    DB::insertList('shop_group', array($newCat),TRUE);
                }

                $u  = new DB('shop_group', 'sg');
                $u->select('sg.id');
//                $u->leftJoin('shop_group_tree sgt', 'sgt.id_child = sg.id');
                $u->where("sg.code_gr = '?'", $code_gr);
//                $u->andWhere('sgt.id_parent = ?', $parent);
                $id = $u->fetchOne();
                unset($u);

                $_SESSION["getId"]['code_gr'][$code_gr]           = $id['id'];
                $_SESSION["getId"]["path_group"][$path_new_group] = $id['id'];
            }
        }
    } // ДОБАВИТЬ КАТЕГОРИЮ

    private function generationGroupCode($ar_pop_wa)
    {
        /** Генерация кода группы, с сверкой по сесии  $_SESSION["getId"]['code_gr']
         * @param  $ar_pop_wa     имя группы на килилице
         * @param  $ar_pop_wa_new имя группы на латыни
         * @return $code          версия кода "<код>, <код>-1, <код>-2, <код>-3"
         */
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');

        $ar_pop_wa_new = strtolower(se_translite_url($ar_pop_wa));
        $stop = false;
        $num  = 0;
        $code = $ar_pop_wa_new;

        while ($stop == false){
            if(!$_SESSION["getId"]['code_gr'][$code]) {
                return $code;
            } else {
                $num += 1;
                $code = $ar_pop_wa_new."-".$num;
            }
        }
    } // Генерация кода группы

    private function check($id, $type = 'category')
    {
        /** Проверить */
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');
        foreach ($this->importData[$type] as $cat)
            if ($cat['id'] == $id) return TRUE;
        return FALSE;
    } // Проверить

    public function getNumberRowsFromFile($file, $extension,$prepare,$chunksize)
    {
        /**
         * INFO Определить количество циклов в импортируемом файле
         *
         * @link https://stackoverflow.com/questions/47987034/get-row-count-with-data-of-excel-sheet-by-using-phpexcel?rq=1
         * @link php number of lines in csv  https://stackoverflow.com/questions/21447329/how-can-i-get-the-total-number-of-rows-in-a-csv-file-with-php
         *
         * @param string $file         путь к файлу с именем файла
         * @param string $extension    тип файла xlsx/csv
         * @param bool $prepare        превью
         * @param integer $chunksize   объем цикла
         * @throws \Exception
         */
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');

        if($extension == 'xlsx' and $prepare != TRUE and $_SESSION["cycleNum"] == 0) {
            $objReader         = PHPExcel_IOFactory::createReader("Excel2007");
            $worksheetData     = $objReader->listWorksheetInfo($file);
            $totalRows         = $worksheetData[0]['totalRows'];
            //$totalColumns      = $worksheetData[0]['totalColumns'];
            $pages             = ceil($totalRows / $chunksize);
            $_SESSION["pages"] = $pages;
        } elseif($extension == 'csv' and $prepare != TRUE and empty($_SESSION["pages"])) {
            $totalRows         = file($file);
            $totalRows         = count($totalRows);
            $pages             = ceil(ceil(($totalRows / $chunksize))/2 +1); /** +1 - заглушка для неразбитой загрузки */
            $_SESSION["pages"] = $pages;
        }
    } // INFO Определить количество циклов в импортируемом файле

    public function getDataFromFile($filename, $options, $prepare)
    {
        /** Получить данные из файла
         *
         * @param $filename
         * @param array  $options                          параметры пользователя из первого шага импорта
         * @param bool   $prepare                          true - режим предпросмотра
         * @param int    $startRow                         стартовая строка
         * @param obj    $objPHPExcel                      объект с ячейками эксель
         * @param int    $_SESSION["countPages"]           общее количество страниц
         * @metod public writTempFiles($writer, $cycleNum) запись временного файла
         * @return bool
         * @throws \Exception
         */

        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');
        try{
            $temporaryFilePath = DOCUMENT_ROOT . "/files/tempfiles/";
            $file              = $temporaryFilePath.$filename;
            if(file_exists($file) and is_readable($file)){
                $extension = pathinfo($file, PATHINFO_EXTENSION);

                if ($prepare != TRUE)  $chunksize = 1000; /** объем временного файла */
                else                   $chunksize = 1000; /** объем врем файла для превью */

                if($extension == 'xlsx') {
                    $this->getNumberRowsFromFile($file, $extension, $prepare, $chunksize);

                    if (class_exists('PHPExcel_IOFactory', TRUE)) {
                        $startRow  = ($chunksize * $_SESSION["countPages"]);

                        PHPExcel_Autoloader::Load('PHPExcel_IOFactory');
                        $obj_reader = PHPExcel_IOFactory::createReader('Excel2007');
                        $obj_reader->setReadDataOnly(true);

                        $chunkFilter = new ReadFilter();
                        $obj_reader->setReadFilter($chunkFilter);
                        $chunkFilter->setRows($startRow+1,$chunksize);

                        $objPHPExcel = $obj_reader->load($file);
                        $rows        = $objPHPExcel->setActiveSheetIndex(0)->toArray();
                        $rows        = array_slice($rows, $startRow);

                        $this->writTempFiles($rows, $_SESSION["countPages"]);
                        if ($prepare != TRUE and count($rows) == $chunksize) {
                            $_SESSION["countPages"]     += 1;
                        } elseif ($prepare != TRUE) {
                            $_SESSION["countPages"]     += 1;
                            $_SESSION["cycleNum"]       += 1;
                        } elseif ($_SESSION["countPages"] == 0 and count($rows) < 1)
                            return FALSE;
                        return TRUE;
                    }
                } elseif($extension == 'csv') {
                    $this->getNumberRowsFromFile($file, $extension, $prepare, $chunksize);
                    /** если импортируем csv */
                    $delimiter = $options['delimiter'];
                    // $encoding = $options['encoding'];

                    /** автоопределитель разделителя
                     * (чувствителен к порядку знаков - по убывающей приоритетности) */
                    if($delimiter == 'auto') {
                        $delimiters_first_line = array('\t' => 0,
                                                       ';'  => 0,
                                                       ':'  => 0);
                        $delimiters_second_line = $delimiters_first_line;
                        $delimiters_final       = array();

                        /** читаем первые 2 строки для обработки */
                        $handle      = fopen($file, 'r');
                        $first_line  = fgets($handle);
                        $second_line = fgets($handle);
                        fclose($handle);

                        /** производим подсчет знаков из $delimiters_first/second_line в обеих строках */
                        foreach ($delimiters_first_line as $delimiter => &$count)
                            $count = count(str_getcsv($first_line, $delimiter, $options['limiterField']));
                        foreach ($delimiters_second_line as $delimiter => &$count)
                            $count = count(str_getcsv($second_line, $delimiter, $options['limiterField']));
                        $delimiter = array_search(max($delimiters_first_line), $delimiters_first_line);

                        /** сопоставляем колво знаков - совпадает, в $delimiters_final */
                        foreach($delimiters_first_line as $key => $value) {
                            if($delimiters_first_line[$key] == $delimiters_second_line[$key])
                                $delimiters_final[$key] = $value;
                        };

                        /** получаем максимальное совпадение из $delimiters_final - переназначаем разделитель с ";" */
                        if(count($delimiters_final) > 1) {
                            $delimiters_final2 = array_keys($delimiters_final, max($delimiters_final));
                            foreach($delimiters_final2 as $value) $delimiter = $value;
                        } else
                            foreach($delimiters_final as $key => $value) $delimiter = $key;
                    };

                    /** формируем массив */
                    $row = 0;
                    $cycleNum = 0;
                    if (($handle = fopen($file, "r")) !== FALSE) {
                        /**
                         * @var $handle                          Корректный файловый указатель на файл
                         * @var int $length                      макс длина строки
                         * @var string $delimiter                разделитель ячеек
                         * @var string $options['limiterField']  разделитель вложенного текста
                         * @var array $line                      массив ячеек строчки
                         *     перебирает все строчки через while
                         */
                        while (($line = fgetcsv($handle, 10000, $delimiter, $options['limiterField'])) !== FALSE) {

                            $num = count($line);
                            $this->data[$row] = array();

                            for ($c=0; $c < $num; $c++) {
                                $auto_encoding = mb_check_encoding($line[$c],'UTF-8');
                                if($auto_encoding != 1) {
                                    $cell = mb_convert_encoding($line[$c], 'UTF-8', "windows-1251");
                                } else $cell = $line[$c];
                                $this->data[$row][$c] = $cell;
                            }
                            if ($row >= $chunksize and $prepare == TRUE) {
                                $this->writTempFiles($this->data, $cycleNum);
                                $row = 0;
                                $this->data = array();
                                break;
                            } elseif ($row >= $chunksize) {
                                /** если не превью */
                                $this->writTempFiles($this->data, $cycleNum);
                                $cycleNum += 1;
                                $row = 0;
                                $this->data = array();
                            } else $row++;
                        }
                        if ($row > 0) {
                            $this->writTempFiles($this->data, $cycleNum); /** запись остатка */
                            $cycleNum += 1;
                        }
                        fclose($handle);
                        $_SESSION["countPages"] = 1; /** заглушка для прогресс бара */
                        $_SESSION["cycleNum"]  += 1;



                        // $this->writTempFiles($rows, $_SESSION["countPages"]);
                        // if ($prepare != TRUE and count($rows) == $chunksize) {
                        //     $_SESSION["countPages"]     += 1;
                        // } elseif ($prepare != TRUE) {
                        //     $_SESSION["countPages"]     += 1;
                        //     $_SESSION["cycleNum"]       += 1;
                        // } elseif ($_SESSION["countPages"] == 0 and count($rows) < 1)
                        //     return FALSE;
                        // return TRUE;



                        if (count($this->data) < 2) {
                            return false;
                        }
                        return true;
                    }
                }
                else
                    writeLog('НЕ КОРРЕКТНОЕ РАСШИРЕНИЕ ФАЙЛА '. $file);
            } else {
                writeLog('ФАЙЛА НЕТ '. $file);
            }
        } catch (Exception $e) {
            writeLog($e->getMessage());
        }
        return true;
    } // Получить данные из файла

    private function prepareData($userData, $options, $prepare=false)
    {
        /** привязываем Заголовки к Номерам Столбцов
         *
         * 1 если приходит пользовательская редакция - подставляем. Иначе берем стандартно
         * 2 соотносим поля с ключами, если нет среди ключей и есть "#" - определяем как модификацию
         * 3 проверка на модификацию и ее корректность
         *
         * Готовим данные
         * @param array $userData              пользовательские значения со второго шага
         * @param $options
         * @param array $rusFields             именной массив столбцов РусНазван-ключНазван  [Ид.] => id ...
         * @param array $this->fields          ассоц массив код=>руссНазвание столбцов
         * @param array $this->fieldsMap       именной массив ключЗаголов-номерСтолбцов [id] => 0  [path_group] => 1 ...
         * @param array $_SESSION["fieldsMap"] именной массив ключЗаголов-номерСтолбцов [id] => 0  [path_group] => 1 ...
         * @param int   $amountElements        колво эллементов в заголовке модификации (при правильном заполнении файла, должно быть две)
         * @param str   $field                 название столбца. Если столбец - модификация - <группа модификации>#<параметр модификации>  <shop_modifications_group> и <shop_feature>
         * @param array $array                 массив названий столбцов
         */

        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');

        /** 1 если приходит пользовательская редакция - подставляем. Иначе берем стандартно */
        if (!$prepare) {
            if (!empty($userData)) {
                $array = $userData;
                if($options['skip'] > 0)
                    foreach (range(0, $options['skip']-1) as $number)
                        array_shift($this->data);
            } else {
                $array = array_shift($this->data);
                if($options['skip'] > 1)
                    foreach (range(0, $options['skip']-2) as $number)
                        array_shift($this->data);
                elseif($options['skip'] > 0)
                    foreach (range(0, $options['skip']-1) as $number)
                        array_shift($this->data);
            }
        } else $array = $this->data[0];

        /** 2 соотносим поля с ключами, если нет среди ключей и есть "#" - определяем как модификацию */
        $rusFields = array();
        foreach ($this->fields as $name => $rus)
            $rusFields[$rus] = $name;

        $arrayNullValues = true;
        foreach ($array as $key => $field) {
            if ($rusFields[$field]) {
                $name = $rusFields[$field];
                $this->fieldsMap[$name] = $key;
                $arrayNullValues = false;
            } elseif (preg_match("/[^\s]+#(?!\s+)/ui",$field)) {
                /** 3 проверка на модификацию и ее корректность*/
                $amountElements = explode('#',$field);
                if (count($amountElements)==2 and $amountElements[0]!='' and $amountElements[1]!='') {
                    $this->fieldsMap[$field] = $key;
                    $arrayNullValues = false;
                } elseif (count($amountElements)>2) {
                    $nameNum = $this->getNameFromNumber($key);
                    $_SESSION['errors']['headline'] = "ПРИМЕЧАНИЕ[стлб. ".$nameNum."]: Ошибка заголовка столбца модификации!";
                    //throw new Exception($this->error);
                } else {}
            }
        }

        /** при всех пустых значениях массива (строка заголовков пустая и куки заголовков), выдать ошибку */
        if ($arrayNullValues == true) {
            $_SESSION['errors']['headline'] = "ПРИМЕЧАНИЕ: заголовки столбцов не заданы!";
        }

        $_SESSION["fieldsMap"] = $this->fieldsMap;
    } // привязываем Заголовки к Номерам Столбцов

    private function getNameFromNumber($num)
    {
        /** получение буквенного номера столбца Excel */
        $numeric=$num%26;
        $letter =chr(65+$numeric);
        $num2   =intval($num/26);
        if ($num2 > 0)  return $this->getNameFromNumber($num2-1).$letter;
        else            return $letter;
    } // получить буквенный номер

    private function communications()
    {
        /** Подготовка данных по связям __товаров__ с __группами__ */
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');

        $u = new DB('shop_group', 'sg');
        $u->reConnection();  /** перезагрузка запроса */
        if ($_SESSION['coreVersion'] > 520) {
            $u->select('
                sg.id,
                GROUP_CONCAT(
                    sgp.name
                    ORDER BY sgt.level
                    SEPARATOR "/"
                ) name,
                sg.code_gr
            ');
            $u->innerJoin("shop_group_tree sgt",   "sg.id = sgt.id_child"   );
            $u->innerJoin("shop_group sgp",        "sgp.id = sgt.id_parent" );
            $u->orderBy('sgt.level');
        } else {
            $u->select('sg.*');
            $u->orderBy('sg.id');
        }
        $u->groupBy('sg.id');
        $groups = $u->getList();
        unset($u);

        foreach ($groups as $key => $item) {
            $_SESSION["getId"]["code_group"][$item['codeGr']] = $item['id'];
            $_SESSION["getId"]["path_group"][$item['name']]   = $item['id'];
        }
    } // Подготовка данных по связям __товаров__ с __группами__

    private function iteratorData($prepare = false, $options)
    {
        /** Данные итератора
         *
         * 1 пропускаем строки
         * 2 $this->importData['prepare'] передает:
         *   первый подмассив: названия столбцов,
         *   второй подмассив: значения первой строки (так понимаю - превью)
         *   третий подмассив: значения для таблиц sql
         * 3 построчная обработка в БД
         * 4 если заголовок отсутствует - ставим заглушку
         * 4.1 добавляем модификации в соотношения, если нет среди ключей и есть "#" - определяем как модификацию
         *
         * @param int $_SESSION['lastProcessedLine'] последняя обработанная строка (без превью)
         */
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');

        /** номер строки файла */
        $i=0;
        $skip=0;

        foreach ($this->data as $key =>  $item){
            /** 1 пропускаем строки */
            if ($i > 0 and $skip < $options['skip']-1) {
                $skip++;
            } else {

                if ($prepare) {

                    /** 2 $this->importData['prepare'] передает: ... */
                    if($options['skip'] != 0 or $i != 0) { /** если в файле есть заголовок */
                        $this->importData['prepare'][$i] = $item;
                    } else {
                        $i++;
                        $this->importData['prepare'][$i] = $item;
                    }

                    $i++;
                    if ($i > 1) {
                        $this->importData['prepare'][2] = array_flip($this->fields);
                        break;
                    }

                } else {
                    /** 3 построчная обработка в БД */
                    if ($this->rowCheck($item) == TRUE)  $this->getRightData($item, $key);
                    $this->data[$key] = null;
                }

            }
        }
        if($prepare==false) $_SESSION['lastProcessedLine'] += count($this->data);
        /** 4 если заголовок отсутствует - ставим заглушку */
        if(empty($this->importData['prepare'][0])) {
            $count = count($this->importData['prepare'][1]);
            $this->importData['prepare'][0] = array_fill( 0, $count , null);

        } else {
            /** 4.1 добавляем модификации в соотношения, если нет среди ключей и есть "#" - определяем как модификацию */
            $rusFields = array();
            foreach ($this->fields as $name => $rus)
                $rusFields[$rus] = $name;
            foreach ($this->importData['prepare'][0] as $key => $field) {
                if (!$rusFields[$field] and strripos($field, '#')) {
                    $this->importData['prepare'][2][$field] = $field;
                }
            }
        }
    } // Данные итератора

    private function getId($key = 'id', $delimiter, $reconTable, $column, $item)
    {
        /** Получение ИД от Имени/Кода...
         * (поддерживает переменные и списки, когда в ячейке эксель несколько значений)
         *
         * 1 разбиение переменной на список
         * 2 если осталась строкой - приводим к общему формату
         * 3 получение данных из таблицы
         * 4 сопоставление вход_списка с данными из таблицы
         *
         * @param $key        ключ
         * @param $delimiter  разделитель
         * @param $reconTable таблСверки
         * @param $column     колонка
         * @param $item       значение
         */
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');

        if(isset($item[$this->fieldsMap[$key]]) and !empty($item[$this->fieldsMap[$key]])) {

            /** 1 разбиение переменной на список */
            $listObj = $item[$this->fieldsMap[$key]];
            if ($delimiter !== FALSE) $listObj = preg_split($delimiter, $listObj);

            /** 2 если осталась строкой - приводим к общему формату */
            if(gettype($listObj) == string or gettype($listObj) == integer) $listObj = array($listObj);

            /** 3 получение данных из таблицы */
            $objects = NULL;
            $u = new DB($reconTable, $reconTable);
            $u->select($reconTable.'.id Id, '.$reconTable.'.'.$column);
            $u->groupBy($reconTable.'.id');
            $u->orderBy($reconTable.'.id');
            $objects = $u->getList();
            unset($u);

            /** 4 сопоставление вход_списка с данными из таблицы */
            $get = array();
            foreach ($listObj as $lObj) {
                $code = $_SESSION["getId"][$key][$lObj]; /** пробуем достать связку из сессии (для быстродействия) */
                if ($code == "") {
                    $code = NULL;
                    foreach ($objects as $object) {
                        $this->debugging('special', __FUNCTION__ . ' ' . __LINE__, __CLASS__, $object[id] . ' ' . $object[$this->convJS($column)]);

                        /** trim: удаление пробелов в начале и конце строки : mb_strtolower: к нижнему регистру */
                        $lObj = mb_strtolower(trim($lObj));
                        $obj = mb_strtolower(trim($object[$this->convJS($column)]));

                        if ($lObj == $obj) {
                            $code = (int)$object[id];
                            if ($code != null)
                                $_SESSION["getId"][$key][$lObj] = $code; /** запоминаем связку в сессии (для быстродействия) */
                        }
                    }
                }
                $code = $this->createBrand($lObj,$key,$code);
                array_push($get, $code);
            }

            if (count($get) == 1)
                $get = $get[0];

            return $get;

        } else
            return NULL;
    } // Получение ИД от Имени/Кода...

    private static function createBrand($lObj,$key,$id)
    {

        /**
         * создаем бренд, если не существует
         * @param  str $lObj имя бренда
         * @param  str $key  столбец (код)
         * @param  int $id   ид бренда в shop_brand
         * @return int $id
         */

        if ($key == 'id_brand' and $id == null) {
            try {
                $data['name'] = $lObj;
                $data['code'] = strtolower(se_translite_url($lObj));
                $u = new DB('shop_brand');
                $u->setValuesFields($data);
                $id = $u->save();
                $_SESSION["getId"][$key][$lObj] = $id; /** запоминаем связку в сессии (для быстродействия) */
                return $id;
            } catch (Exception $e) {
                /** "Двухсловный бренд" и "Двухсловный   бренд" - скриптом распозн как разн назван, БД - как одно */
                return null;
            }
        } else
            return $id;

    } // создаем бренд, если не существует

    private function getIdGroup($key = 'id', $delimiter, $item)
    {
        /** Получение ид группы
         *
         *
         * @@@@@@ @@@@@@ @@@@@@@@    @@@@@@ @@@@@@     @@@@@@ @@@@@@ @@@@@@ @@  @@ @@@@@@
         * @@     @@        @@         @@   @@   @@    @@     @@  @@ @@  @@ @@  @@ @@  @@
         * @@     @@@@@@    @@         @@   @@   @@    @@     @@@@@@ @@  @@ @@  @@ @@@@@@
         * @@  @@ @@        @@         @@   @@   @@    @@  @@ @@ @@  @@  @@ @@  @@ @@
         * @@@@@@ @@@@@@    @@       @@@@@@ @@@@@@     @@@@@@ @@  @@ @@@@@@ @@@@@@ @@
         *
         *
         * 1 разбиение переменной на список, если осталась строкой - приводим к общему формату
         * 2 сопоставление вход_списка с данными из таблицы
         *
         * @param $key ключ
         * @param $delimiter разделитель
         * @param $item таблСверки
         * ключ / разделитель / таблСверки / колонка / значение
         */
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');

        if(isset($item[$this->fieldsMap[$key]]) and !empty($item[$this->fieldsMap[$key]])) {
            /** 1 разбиение переменной на список, если осталась строкой - приводим к общему формату */
            $listObj = $item[$this->fieldsMap[$key]];
            if ($delimiter !== FALSE) $listObj = preg_split($delimiter, $listObj);
            if(gettype($listObj) == string or gettype($listObj) == integer) $listObj = array($listObj);

            /** 2 сопоставление вход_списка с данными из таблицы */
            $get = array();
            foreach ($listObj as $lObj) {
                $code = $_SESSION["getId"][$key][$lObj];
                array_push($get, $code);
            }
            if (count($get) == 1)
                $get = $get[0];
            return $get;

        } else
            return NULL;
    } // ПОЛУЧИТЬ ID ГРУППЫ

    private function get( $key = 'id', $delimiter, $item)
    {
        /** Получить
         *
         * 1 разложение строки на элементы
         * 2 если осталась строкой - приводим к общему формату
         * 3 если item-ов 0 - передаем NULL
         *   один - вытаскиваем его из массива и передаем
         *   в ином случае передаем массив item-ов
         */
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');


        /** 1 разложение строки на элементы */
        if(gettype($item[$this->fieldsMap[$key]]) == string and $delimiter !== FALSE)
            $fieldsM = preg_split($delimiter, $item[$this->fieldsMap[$key]]);
        else
            $fieldsM = array((string) $item[$this->fieldsMap[$key]]);
        /** 2 если осталась строкой - приводим к общему формату */
        if(gettype($fieldsM) == string or gettype($fieldsM) == integer) $fieldsM = array($fieldsM);


        $itemFinish = array();
        foreach ($fieldsM as $fM) {
            if($fM == '') $fM = NULL;
            //if (isset($fM) and !empty($fM))
            array_push($itemFinish, $fM); /** добавить элемент в массив */
        }

        /** 3 если item-ов 0 - передаем NULL ... */
        if(count($itemFinish) == 0)     return NULL;
        elseif(count($itemFinish) == 1) return $itemFinish[0];
        else                            return $itemFinish;
    } // Получить

    private function CommunGroup($item, $creatGroup)
    {
        /** Создание Групп и Связей с ними товаров
         *
         *
         * @@@@@@ @@@@@@ @@     @@    @@@@@@ @@@@@@ @@@@@@ @@  @@ @@@@@@
         * @@     @@  @@ @@@   @@@    @@     @@  @@ @@  @@ @@  @@ @@  @@
         * @@     @@  @@ @@ @@@ @@    @@     @@@@@@ @@  @@ @@  @@ @@@@@@
         * @@     @@  @@ @@  @  @@    @@  @@ @@ @@  @@  @@ @@  @@ @@
         * @@@@@@ @@@@@@ @@     @@    @@@@@@ @@  @@ @@@@@@ @@@@@@ @@
         *
         *
         * 1 получаем данные для обработки
         * 2 унифицируем
         * 3 если данные есть, но абсолютно отсутствует информация по id:
         *   приравниваем $id_gr_cg к массиву для прохождения его через условия
         * 4 проверка совпадения длины не пустых столбцов - если не совпадают, инициализируем 501 ошибку
         * 5 сверяем id с базой (если присутствуют)
         *   ...если отсутствуют, сверяем коды с базой (если присутствуют)
         *   ...если отсутствуют, сверяем имена с базой (если присутствуют)
         *   ...если отсутствуют, добавляем категории и передаем данные для последующей привязки
         *      (если в базе не найдены совпадения)
         * 6 унифицируем конечный результат
         */
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');

        // // фильтрация путей групп - получение значений после последних слешей
        // $listObj = $item[$this->fieldsMap['path_group']];
        // $listObj = preg_split("/,(?!\s+)/ui", $listObj);
        // foreach($listObj as &$lo) {
        //     $lo = explode("/", $lo);
        //     if(gettype($lo) == 'array') $lo = array_pop($lo);
        // }
        // $item[$this->fieldsMap['path_group']] = implode(",", $listObj);

        /** 1 получаем данные для обработки */
        $id_gr_ig   = array(NULL);
        $id_gr_cg   = $this->getIdGroup('code_group', "/,(?!\s+)/ui", $item);
        $id_gr_pg   = $this->getIdGroup('path_group', "/,(?!\s+)/ui", $item);

        $code_group = $this->get('code_group', "/,(?!\s+)/ui", $item);
        $path_group = $this->get('path_group', "NULL_delimiter", $item);
        /** 2 унифицируем */
        if(gettype($code_group) != 'array' and $code_group != NULL) $code_group = array($code_group);
        if(gettype($path_group) != 'array' and $path_group != NULL) $path_group = array($path_group);
        /** 3 если данные есть, но абсолютно отсутствует информация по id: ... */
        if(gettype($id_gr_cg  ) != 'array') $id_gr_cg = array($id_gr_cg);

        // // распечатываем по id товара // тест
        // if($item[0] == 13) {
        //     //writeLog('$path_group');writeLog($path_group);
        //     //writeLog('$id_gr_cg');writeLog($id_gr_cg);
        // };

        /** 4 проверка совпадения длины не пустых столбцов - если не совпадают, инициализируем 501 ошибку */
        $error = FALSE;
        if($error == TRUE){
            header("HTTP/1.1 501 Not Implemented");
            echo 'Не корректные данные в импортируемом файле, количество пареметров в столбцах групп не совпадает!';
            exit;
        }

        /**
         * если имеем дело с массивом - отсеиваем группы со всеми пустыми параметрами
         *
         * измеряем длину массивов по id, коду и пути
         * определяем наибольшее значение
         * Array        Array           Array
         * (            (               (
         *     [0] =>       [0] => 12       [0] => dub
         *     [1] =>       [1] => 15       [1] => dizajn-1
         * )            )               )
         * проходим по наибольшему значению ранжированием
         * если все значения в строке "таблицы" пустые - удаляем
         */
        if(gettype($code_group) == 'array') {
            $cou_id_gr_ig   = count($id_gr_ig);
            $cou_id_gr_cg   = count($id_gr_cg);
            $cou_code_group = count($code_group);
            $cou_min = max($cou_id_gr_ig, $cou_id_gr_cg, $cou_code_group);
            /** инициализируем список NULL id, равный длине $id_gr_cg */
            $id_gr_ig = array_fill(0, $cou_min, NULL);
            $range = range(0, $cou_min - 1, 1);
            foreach($range as $ran){
                if($id_gr_ig[$ran] == NULL and $id_gr_cg[$ran] == NULL and $code_group[$ran] == NULL){
                    unset($id_gr_ig[$ran]);    $id_gr_ig = array_values($id_gr_ig);
                    unset($id_gr_cg[$ran]);    $id_gr_cg = array_values($id_gr_cg);
                    unset($code_group[$ran]);  $code_group = array_values($code_group);
                };
            };
        };

        /** 5 сверяем id с базой (если присутствуют) ... */
        $id_gr = $id_gr_ig;
        if(gettype($id_gr) == 'array'){
            $start = 0;
            foreach($id_gr as $i)
                if($i == NULL) $start = $start + 1;
            if($start != 0)    $id_gr = $id_gr_cg;
        };
        if(gettype($id_gr) == 'array'){
            $start = 0;
            foreach($id_gr as $i)
                if($i == NULL) $start = $start + 1;
            if($start != 0)    $id_gr = $id_gr_pg;
        };


        if($creatGroup == TRUE) {
            if (getType($code_group) == 'array' or getType($path_group) == 'array') {

                /** унифицируем */
                if(gettype($id_gr)      != 'array') $id_gr      = array($id_gr);
                if(getType($code_group) != 'array') $code_group = array($code_group);
                $path_group = $item[$this->fieldsMap['path_group']];
                $listObj = preg_split("/,(?!\s+)/ui", $path_group);
                if(getType($path_group) != 'array') $path_group = array($path_group);

                $start = 0;
                foreach ($id_gr as $i)
                    if ($i == NULL) $start = $start + 1;

                if ($start != 0) {
                    $this->addCategoryMain($code_group, $listObj);
                    $id_gr = array($item);
                };
            };
        };

        /** 6 унифицируем конечный результат */
        if(gettype($id_gr) == integer) $id_gr = array($id_gr);
        return $id_gr;
    } // СОЗДАНИЕ ГРУПП И СВЯЗЕЙ С ТОВАРАМИ

    private function rowCheck($item)
    {
        /**
         * Проверка заполненности строки
         * @param  $item  массив ячеек строки
         * @return bool   TRUE - не пустая
         */
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');

        $startGetRightData = FALSE;
        foreach($item as $k => $v)
            if (!empty($v)) {
                $startGetRightData = TRUE;
                break;
            }
        return $startGetRightData;
    } // Проверка заполненности строки

    private function creationFeature($Product, $item, $code)
    {
        /** Cверяем наличие характеристик и значений <shop_feature> <shop_feature_value_list>.
         *
         * @@@@@@ @@@@@@ @@@@@@    @@    @@@@@@@@    @@@@@@ @@@@@@    @@    @@@@@@@@
         * @@     @@  @@ @@       @@@@      @@       @@     @@       @@@@      @@
         * @@     @@@@@@ @@@@@@  @@  @@     @@       @@@@@@ @@@@@@  @@  @@     @@
         * @@     @@ @@  @@     @@@@@@@@    @@       @@     @@     @@@@@@@@    @@
         * @@@@@@ @@  @@ @@@@@@ @@    @@    @@       @@     @@@@@@ @@    @@    @@
         *
         * если нет - создаем;
         * Заменяем значения на id
         *
         * @param array $Product все параметры продукта (одной строчки)
         * param array $item нужен для получения id товара
         */
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');

        $lineNum = $Product['lineNum'];  /** получаем номер строки из массива  1,2,3,4 */

        $features = preg_split("/,(?!\s+)/ui", $Product["features"]);
        $Product["features"] = array();
        foreach ($features as $k => $i) {
            $featuresValue = explode("#", $i);
            $Product["features"][$featuresValue[0]] = array("value"=>$featuresValue[1],"type"=>$featuresValue[2]);
        }
        unset($features);
        $Product["features"] = $this->featureReconciliation($Product["features"], "sf", $lineNum);
        $Product["features"] = $this->featureValueReconciliation($Product["features"], "sfvl", $lineNum);


        /** выстраиваем формат сохранения */
        $insertListFeatures = array();
        foreach ($Product["features"] as $k => $i) {

            $value = $i["value"];
            if ($i["type"]) $type=$i["type"]; else $type = 'list';

            $inserUnut = array(
                "id_price"     => $this->get('id', "/,(?!\s+)/ui", $item),
                "id_feature"   => $k,
                "value_string" => null,
                "value_number" => null,
                "value_bool"   => null,
                "id_value"     => null,
            );
            if     ($type=="string")    $inserUnut["value_string"]=$value;
            elseif ($type=="number")    $inserUnut["value_number"]=$value;
            elseif ($type=="bool")      $inserUnut["value_bool"]=$value;
            elseif ($type=="list")      $inserUnut["id_value"]=$value;
            elseif ($type=="colorlist") $inserUnut["id_value"]=$value;

            array_push($insertListFeatures, $inserUnut);
            unset($value,$type);
        }
        $this->importData['features'][$code] = $insertListFeatures;
        unset($Product["features"]);

        return $Product;
    } // СОЗДАНИЕ ХАРАКТЕРИСТИК Cверяем наличие характеристик и значений

    private function featureReconciliation($features, $section, $lineNum)
    {
        /** Проверка наличия характеристики в БД <shop_feature> или ее создание */
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');

        $nameFeatures = array_keys($features);
        foreach ($nameFeatures as $k => $i) {

            if (!empty($i)) {
                $value = $i;
                if ($i["type"]) $type=$features[$i]["type"]; else $type = 'list';

                /** ищем связку в сессии - присваиваем id */
                if ($this->feature[$section][$value]) {
                    $id = $this->feature[$section][$value];
                    $features[$id] = $features[$value];

                } else {

                    /** если нет характеристики - создаем и добавляем в сессию, присваиваем id */

                    $newfeat = array('name' => $value, 'type' => $type);

                    if ($newfeat['name'] and $newfeat['type']) {
                        DB::insertList('shop_feature', array($newfeat),TRUE);

                        $u = new DB("shop_feature", "sf");
                        $u->select("sf.id");
                        $u->where("sf.name = '$value'");
                        $list = $u->getList();
                        unset($u);

                        $id = $list[0]['id'];
                        $this->feature[$section][$value] = $id;
                        $features[$id] = $features[$value];
                    } elseif ($newfeat['name'] or $newfeat['type']) {
                        /** вывод ошибки с линией */
                        $line = $lineNum;
                        if (!$_SESSION['errors']['feature']) $_SESSION['errors']['feature'] = 'ПРИМЕЧАНИЕ[стр. '.$line.']: не корректное заполнение столбца "Характеристики"';
                    } else {};
                }
                unset($features[$i],$value,$type);
            }
        }

        return $features;
    } // Проверка наличия характеристики в БД

    private function featureValueReconciliation($features, $section, $lineNum)
    {
        /** Проверка наличия значения характеристики в БД <shop_feature_value_list> или ее создание */
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');

        $nameFeatures = $features;

        foreach ($nameFeatures as $k => $i) {
            $value = $i["value"];

            if (!empty($value) and !empty($k)) {
                if ($i["type"]) $type=$i["type"]; else $type = 'list';

                if ($type=='list' or $type=='colorlist') {
                    /** ищем связку в сессии - присваиваем id */
                    if ($this->feature[$section][$value]) {

                        $val = $this->feature[$section][$value];
                        $features[$k] = array("value"=>$val,"type"=>$type);

                    } else {

                        /** если нет значения характеристики - создаем и добавляем в сессию, присваиваем id */

                        $newfeat = array('value' => $value, 'id_feature' => $k);
                        if ($newfeat['value'] and $newfeat['id_feature']) {
                            DB::insertList('shop_feature_value_list', array($newfeat),TRUE);

                            $u = new DB("shop_feature_value_list", "sfvl");
                            $u->select("sfvl.id");
                            $u->where("sfvl.value = '$value'");
                            $list = $u->getList();
                            unset($u);

                            $val = $list[0]['id'];
                            $this->feature[$section][$value] = $val;
                            $features[$k] = array("value"=>$val,"type"=>$type);
                        } elseif ($newfeat['name'] or $newfeat['type']) {
                            /** вывод ошибки с линией */
                            $line = $lineNum;
                            if (!$_SESSION['errors']['feature']) $_SESSION['errors']['feature'] = 'ПРИМЕЧАНИЕ[стр. '.$line.']: не корректное заполнение столбца "Характеристики"';
                        } else {};

                    }
                    unset($value,$type);
                }
            }
        }

        return $features;
    } // Проверка наличия значения характеристики в БД

    private function creationModificationsStart($Product, $item)
    {
        /** создание модификации Главная (группы, самой модификации, параметров), передача данных модификации товара
         *
         * @@@@@@ @@@@@@ @@@@@@    @@    @@@@@@@@    @@     @@ @@@@@@ @@@@@@
         * @@     @@  @@ @@       @@@@      @@       @@@   @@@ @@  @@ @@   @@
         * @@     @@@@@@ @@@@@@  @@  @@     @@       @@ @@@ @@ @@  @@ @@   @@
         * @@     @@ @@  @@     @@@@@@@@    @@       @@  @  @@ @@  @@ @@   @@
         * @@@@@@ @@  @@ @@@@@@ @@    @@    @@       @@     @@ @@@@@@ @@@@@@
         *
         * @param array $Product               массив для БД с ключами-значениями
         * @param array $item                  нумерованный массив ячеек строки
         * @param array $_SESSION["fieldsMap"] массив ключ-номер столбца
         * @param array $mod                   все параметры модификации
         * @return array $Product              массив для БД с ключами-значениями
         * @return array $this->modData        данные по модификации
         *
         * 1 получаем массив группа,модификация,значение
         * 2 получаем все параметры модификации и удаляем их из основного массива
         *
         * Таблицы:              shop_modifications_group  shop_modifications_img      shop_feature  shop_modifications
         *                       shop_feature_value_list   shop_modifications_feature  shop_price    shop_group_feature
         * Помимо, достаем ids:  shop_img                  shop_price
         */
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');

        /** 1 получаем массив группа,модификация,значение */

        $this->thereModification[$Product['code']] = false;
        $modParam = array();
        foreach ($_SESSION["fieldsMap"] as $k => $i) {
            if (strripos($k, '#')) {

                $groupMod = explode("#", $k);
                $unit = array(
                    'shop_modifications_group' => $groupMod[0],
                    'shop_feature'            => $groupMod[1],
                    'shop_feature_value_list' => $item[$i],
                );

                if ($item[$i]) {
                    array_push($modParam, $unit);
                    $this->thereModification[$Product['code']] = true;
                }
            }
        }

        if (!empty($modParam)) {

            /** 2 получаем все параметры модификации и удаляем их из основного массива */

            $mod = array(
                'price'          => $Product['price'],
                'price_opt'      => $Product['price_opt'],
                'price_opt_corp' => $Product['price_opt_corp'],
                'price_purchase' => $Product['price_purchase'],
                'bonus'          => $Product['bonus'],

                'article'        => $Product['article'],
                'presence_count' => $Product['presence_count'],
                'img_alt'        => $Product['img_alt'],
                'description'    => $Product['description'],
                'mod_param'      => $modParam,
            );
            unset($modParam);
            foreach (array_keys($mod) as $i) {
                if ($i == 'article' or $i == 'mod_param' or $i == 'img_alt') {
                } elseif ($i == 'presence_count') $Product[$i] = 0;
                else $Product[$i] = "";
            }
            $this->modData[$Product['code']][]=$mod;
        }
        return $Product;
    } // СОЗДАНИЕ МОДИФИКАЦИИ

    private function creationModificationsFinal($idsPrice)
    {
        /**
         * Cоздание модификации Главная
         *
         * 1 получаем модификации, рассортированные по товарам
         * 2 создаем группы модификаций, модификации, параметры,
         *   соединяем <shop_modifications_group> и <shop_feature> (при их отсутствии)
         * 3 Преобразуем данные к записи в БД
         *
         * @param array $idsPrice                  массив идТовара-создание(bull)
         * @param boll $this->thereModification    вкл/выкл генерации модификации
         * @param object $this->modData            данные для генерации модификаций
         */
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');


        /** 1 получаем модификации, рассортированные по товарам */

        $priceMods = array();                                  /** с сортировкой по товарам */
        foreach($idsPrice as $k=>$i)
            if ($this->modData[$k]) $priceMods[$k] = $this->modData[$k];


        if (count($priceMods)>0) {

            /** 2 создаем группы модификаций, модификации, параметры, соединяем (при их отсутствии) */

            $priceMods = $this->createModifGroup($priceMods);  /** shop_modifications_group */
            $priceMods = $this->createFeature($priceMods);     /** shop_feature */
            $priceMods = $this->createModifValue($priceMods);  /** shop_feature_value_list */
            $this->createCommunMod($priceMods);                /** shop_group_feature */


            /** 3 Преобразуем данные к записи в БД */

            $priceMods = $this->checkCreatMod($priceMods, $idsPrice);
            $priceMods = $this->createModifications($priceMods, $idsPrice); /** заполнение shop_modifications */
            $this->createModFeature($priceMods, $idsPrice);                 /** заполнение shop_modifications_feature */
            $this->createModImg($priceMods, $idsPrice);                     /** заполнение shop_modifications_img */
        }

        unset($priceMods);

    } // создание модификации Главная

    private function createModifGroup($priceMods)
    {
        /** создаем группы модификаций (при их отсутствии)
         * @param array $priceMods     все данные
         * @param array $this->feature name-id данные для сверки присутствия в базе
         */
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');


        /** проверяем наличие, если нет - на добавление */

        $newModGroups = array();
        foreach ($priceMods as $priceKey=>$priceUnit) {
            foreach ($priceUnit as $k=>$i) {
                $mod = $priceMods[$priceKey][$k];

                foreach ($mod['mod_param'] as $key => $value) {
                    $value = $value['shop_modifications_group'];
                    if (!$this->feature['smg'][$value])
                        $newModGroups[$value]=array('name'=>$value,'vtype'=>2); /** vtype 2 - заменяет цену товара */
                }
            }
        }
        foreach ($newModGroups as $k=>$i) {
            array_push($newModGroups,$i);
            unset($newModGroups[$k]);
        }


        /** записываем массив */

        if (count($newModGroups)>0) {
            DB::insertList('shop_modifications_group', $newModGroups,TRUE);
            unset($this->feature['smg']);
            $this->getFeatureInquiry("shop_modifications_group","smg","smg.id, smg.name");
        }


        /** получаем id, подставляем */

        foreach ($priceMods as $priceKey=>$priceUnit) {
            foreach ($priceUnit as $k => $i) {
                $mod = $priceMods[$priceKey][$k];

                foreach ($mod['mod_param'] as $key => $value) {
                    $value = $value['shop_modifications_group'];
                    $id = $this->feature['smg'][$value];
                    $mod['mod_param'][$key]['shop_modifications_group'] = $id;
                    $priceMods[$priceKey][$k] = $mod;
                }
            }
        }
        unset($this->feature['smg']);


        return $priceMods;
    } // создаем группы модификаций

    private function createFeature($priceMods)
    {
        /** создаем модификации (при их отсутствии)
         * @param array $priceMods     все данные
         * @param array $this->feature name-id данные для сверки присутствия в базе
         */
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');


        /** проверяем наличие, если нет - на добавление */

        $newMods = array();
        foreach ($priceMods as $priceKey=>$priceUnit) {
            foreach ($priceUnit as $k=>$i) {
                $mod = $priceMods[$priceKey][$k];

                foreach ($mod['mod_param'] as $key => $value) {
                    $value = $value['shop_feature'];
                    if (!$this->feature['sf'][$value])
                        $newMods[$value]=array('name'=>$value, 'type'=>'list');
                }
            }
        }
        foreach ($newMods as $k=>$i) {
            array_push($newMods,$i);
            unset($newMods[$k]);
        }


        /** записываем массив */

        if (count($newMods)>0) {
            DB::insertList('shop_feature', $newMods, TRUE);
            unset($this->feature['sf']);
            $this->getFeatureInquiry("shop_feature", "sf", "sf.id, sf.name");
        }


        /** получаем id, подставляем */

        foreach ($priceMods as $priceKey=>$priceUnit) {
            foreach ($priceUnit as $k => $i) {
                $mod = $priceMods[$priceKey][$k];

                foreach ($mod['mod_param'] as $key => $value) {
                    $value = $value['shop_feature'];
                    $id = $this->feature['sf'][$value];
                    $mod['mod_param'][$key]['shop_feature'] = $id;
                    $priceMods[$priceKey][$k] = $mod;
                }
            }
        }
        unset($this->feature['sf']);


        return $priceMods;
    } // создаем модификации

    private function createModifValue($priceMods)
    {
        /** создаем значения модификаций (при их отсутствии)
         * @param array $priceMods     все данные
         * @param array $this->feature name-id данные для сверки присутствия в базе
         */
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');


        /** проверяем наличие, если нет - на добавление */

        $newModValues = array();
        foreach ($priceMods as $priceKey=>$priceUnit) {
            foreach ($priceUnit as $k=>$i) {
                $mod = $priceMods[$priceKey][$k];

                foreach ($mod['mod_param'] as $key => $value) {
                    $sfvl = $value['shop_feature_value_list'];  $sf = $value['shop_feature'];
                    if (!$this->feature['sfvl'][$sfvl])
                        $newModValues[$sfvl.':'.$sf] = array('value'=>$sfvl, 'id_feature'=>$sf);
                }
            }
        }
        foreach ($newModValues as $k=>$i) {
            array_push($newModValues,$i);
            unset($newModValues[$k]);
        }


        /** записываем массив */

        if (count($newModValues)>0) {
            DB::insertList('shop_feature_value_list', $newModValues, TRUE);
            unset($this->feature['sfvl']);
            $this->getFeatureInquiry("shop_feature_value_list", "sfvl", "sfvl.id, sfvl.value name");
        }


        /** получаем id, подставляем */

        foreach ($priceMods as $priceKey=>$priceUnit) {
            foreach ($priceUnit as $k => $i) {
                $mod = $priceMods[$priceKey][$k];

                foreach ($mod['mod_param'] as $key => $value) {
                    $value = $value['shop_feature_value_list'];
                    $id = $this->feature['sfvl'][$value];
                    $mod['mod_param'][$key]['shop_feature_value_list'] = $id;
                    $priceMods[$priceKey][$k] = $mod;
                }
            }
        }
        unset($this->feature['sfvl']);


        return $priceMods;
    } // создаем значения модификаций

    private function createCommunMod($priceMods)
    {
        /** создаем связи <shop_modifications_group> и <shop_feature> (при их отсутствии)
         * @param array $priceMods     все данные
         * @param array $this->feature name-id данные для сверки присутствия в базе
         */
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');


        /** проверяем наличие, если нет - на добавление */

        $newLigaments = array();
        foreach ($priceMods as $priceKey=>$priceUnit) {
            foreach ($priceUnit as $k=>$i) {
                $mod = $priceMods[$priceKey][$k];

                foreach ($mod['mod_param'] as $key => $value) {
                    $value = $value['shop_modifications_group'].":".$value['shop_feature'];
                    if (!$this->feature['sgf'][$value]) {
                        $idId = explode(':', $value);
                        $newLigaments[$idId[0].':'.$idId[1]] = array('id_group'=>$idId[0],'id_feature'=>$idId[1]);

                    }
                }
            }
        }
        foreach ($newLigaments as $k=>$i) {
            array_push($newLigaments,$i);
            unset($newLigaments[$k]);
        }


        /** записываем массив */

        if (count($newLigaments)>0) {
            DB::insertList('shop_group_feature', $newLigaments, TRUE);
            unset($this->feature['sgf']);
            //$this->getFeatureInquiry("shop_group_feature","sgf","CONCAT_WS(':', sgf.id_group, sgf.id_feature) name, sgf.id");
            //unset($this->feature['sgf']);
        }

    } // создаем связи групп модификаций и характеристик

    private function checkCreatMod($priceMods, $idsPrice)
    {
        /** Проверяем наличие модификации по таблице <shop_modifications_feature>
         * 1 генерируем ключ, например: 47662:148,47663:151
         * 2 Сверяем ключ с БД
         */
        $this->debugging('funct', __FUNCTION__ . ' ' . __LINE__, __CLASS__, '[comment]');


        /** 1 генерируем ключ, например: 47662:148,47663:151 */

        foreach ($priceMods as $priceCode=>$priceUnit) {
            foreach ($priceUnit as $key=>$val) {
                $mod = $priceMods[$priceCode][$key];

                $keyHaving = '';
                $keyArray  = array();
                foreach ($mod['mod_param'] as $k => $i)
                    $keyArray[$i['shop_feature']] = $i['shop_feature'].':'.$i['shop_feature_value_list'];
                ksort($keyArray);
                foreach ($keyArray as $k => $i) $keyHaving = $keyHaving . $i . ',';
                $keyHaving = substr($keyHaving, 0, -1);

                $priceMods[$priceCode][$key]['keyHaving'] = $idsPrice[$priceCode].'#'.$keyHaving;
            }
        }


        /** 2 Сверяем ключ с БД.  ответ БД через query # ответ query */

        $idsProdStr = implode(",", $idsPrice);
        $ca = DB::query("SELECT
                           smf.id,
                           smf.id_price,
                           smf.id_modification idmod,
                           GROUP_CONCAT(smf.id_feature, ':', smf.id_value ORDER BY smf.id_feature) AS ff
                         FROM shop_modifications_feature smf
                         WHERE smf.id_price IN ($idsProdStr) AND smf.id_modification IS NOT NULL
                         GROUP BY smf.id_modification");
        $ca->setFetchMode(PDO::FETCH_ASSOC);
        $checkArray = $ca->fetchAll();


        /** генерируем <shopPriceId>#<shopFeatureId>:<shopFeatureValueListId>,... => <shopModificationsFeatureId> */

        foreach($checkArray as $k=>$i) {
            $key = $i['id_price'].'#'.$i['ff'];
            $checkArray[$key] = $i['idmod'];
            unset($checkArray[$k]);
        }

        foreach ($priceMods as $priceKey=>$priceUnit) {
            foreach ($priceUnit as $key=>$val) {
                $mod = $priceMods[$priceKey][$key];

                $keyHaving = $mod['keyHaving'];
                if ($checkArray[$keyHaving]) {
                    $priceMods[$priceKey][$key]['idModification'] = $checkArray[$keyHaving];
                    $priceMods[$priceKey][$key]['createMod']=false;
                } else {
                    $priceMods[$priceKey][$key]['idModification'] = null;
                    $priceMods[$priceKey][$key]['createMod']=true;
                }
            }
        }

        //writeLog('file');writeLog($priceMods);
        //writeLog('DB');writeLog($checkArray);

        return $priceMods;
    } // проверка наличия модификации <shop_modifications_feature>

    private function createModifications($priceMods, $idsPrice)
    {
        /** заполнение shop_modifications
         * 1 получаем все параметры
         * 2 получаем ids cуществующих записей shop_modifications
         * 3 записываем (если отсутствует)
         * 4 запрашиваем <idPrice>##<idModGroup> => <id> (с фильтрацией по идТовара)
         * 5 заполняем $priceMods ids модификаций
         */
        $this->debugging('funct', __FUNCTION__ . ' ' . __LINE__, __CLASS__, '[comment]');


        /** 1 получаем все параметры */

        $newShopMod = array();
        foreach ($priceMods as $priceKey=>$priceUnit) {
            foreach ($priceUnit as $key=>$val) {
                $mod = $priceMods[$priceKey][$key];
                $idPrice = $idsPrice[$priceKey];
                $priceMods[$priceKey][$key]['idPrice'] = $idPrice;

                if ($mod['createMod']==true) {

                    $shopModifGroup = "";
                    foreach ($mod['mod_param'] as $k => $i) {
                        if (!empty($i['shop_modifications_group'])) {
                            $shopModifGroup = $i['shop_modifications_group'];
                            break;
                        }
                    }

                    /** заполняем, ставим заглушки в поля с null перед записью */
                    $idPrice = $idsPrice[$priceKey];
                    $shMods = array(
                        'id_mod_group'   => $shopModifGroup        ? $shopModifGroup        : null,
                        'id_price'       => $idPrice               ? $idPrice               : null,
                        'code'           => $mod['article']        ? $mod['article']        : "",
                        'value'          => $mod['price']          ? $mod['price']          : 0,
                        'value_opt'      => $mod['price_opt']      ? $mod['price_opt']      : 0,
                        'value_opt_corp' => $mod['price_opt_corp'] ? $mod['price_opt_corp'] : 0,
                        'value_purchase' => $mod['price_purchase'] ? $mod['price_purchase'] : 0,
                        'count'          => $mod['presence_count'] ? $mod['presence_count'] : -1,
                        'description'    => $mod['description']    ? $mod['description']    : "");

                    /** в массив */
                    if ($shMods['id_mod_group']!=null or $shMods['id_price']!=null) {
                        array_push($newShopMod, $shMods);
                    }
                }
            }
        }


        /** 2 получаем ids cуществующих записей shop_modifications */

        $u = new DB("shop_modifications", "sm");
        $u->select("sm.id");
        $exclusionList = $u->getList();
        $excListStr = "";
        foreach ($exclusionList as $k => $i) {
            $excListStr = $excListStr.$i['id'].",";
            unset($exclusionList[$k]);
        }
        $excListStr = substr($excListStr, 0, -1);
        unset($u,$exclusionList);


        /** 3 записываем */

        //$u = new DB("shop_modifications", "sm");
        //$u->setValuesFields($shMods);
        //$idModification = $u->save();

        if (count($newShopMod)>0)
            DB::insertList('shop_modifications', $newShopMod, TRUE);


        /** 4 запрашиваем <idPrice>##<idModGroup> => <id> (с фильтрацией по идТовара) */

        $idsProductsSrt = implode(",", $idsPrice);
        $u = new DB("shop_modifications", "sm");
        $u->select("sm.id, sm.id_price, sm.id_mod_group,
            sm.value price, sm.value_opt price_opt, sm.value_opt_corp price_opt_corp, sm.value_purchase price_purchase,
            sm.count presence_count");
        $u->where("sm.id_price IN ($idsProductsSrt)");
        if ($excListStr!="") $u->andWhere("sm.id NOT IN ($excListStr)");
        $l = $u->getList();
        $list = array();
        foreach ($l as $k => $i) {
            $key = intval($i['idPrice']).'##'.
                   intval($i['idModGroup']).'##'.
                   number_format($i['price'], 2, '.', '').'##'.
                   number_format($i['priceOpt'], 2, '.', '').'##'.
                   number_format($i['priceOptCorp'], 2, '.', '').'##'.
                   number_format($i['pricePurchase'], 2, '.', '').'##'.
                   intval($i['presenceCount']);
            $unit = array('key'=>$key,'id'=>$i['id']);
            array_push($list,$unit);
            unset($l[$k]);
        }
        unset($u,$l,$key,$idsProductsSrt,$idsProducts);


        /** 5 заполняем $priceMods ids модификаций */

        foreach ($priceMods as $priceKey=>$priceUnit) {
            foreach ($priceUnit as $key=>$val) {
                $mod = $priceMods[$priceKey][$key];

                $keyParamGr = intval($mod['idPrice']).'##'.
                              intval($mod['mod_param'][0]['shop_modifications_group']).'##'.
                              number_format($mod['price'], 2, '.', '').'##'.
                              number_format($mod['price_opt'], 2, '.', '').'##'.
                              number_format($mod['price_opt_corp'], 2, '.', '').'##'.
                              number_format($mod['price_purchase'], 2, '.', '').'##'.
                              intval($mod['presence_count']);

                foreach ($list as $k=>$i) {
                    if ($i['key']==$keyParamGr) {
                        $priceMods[$priceKey][$key]['idModification'] = $i['id'];
                        unset($list[$k]); break;
                    }
                }
            }
        }


        return $priceMods;

    } // заполнение shop_modifications

    private function createModFeature($priceMods, $idsPrice)
    {
        /** Записываем значения модификации  <shop_modifications_feature> */
        $this->debugging('funct', __FUNCTION__ . ' ' . __LINE__, __CLASS__, '[comment]');


        $newFeatures = array();
        foreach ($priceMods as $priceCode=>$priceUnit) {
            foreach ($priceUnit as $key=>$val) {
                $mod = $priceMods[$priceCode][$key];

                $priceKey = $idsPrice[$priceCode];
                if ($mod['createMod']==true and $mod['idModification']!=null) {
                    foreach ($mod['mod_param'] as $k => $i) {
                        $shopModificationsFeature = array(
                            'id_price'=>$priceKey, 'id_modification'=>$mod['idModification'], 'id_feature'=>$i['shop_feature'],
                            'id_value'=>$i['shop_feature_value_list']);
                        $keyShopModFeat = '';
                        foreach ($shopModificationsFeature as $KSMF=>$iSMF)  $keyShopModFeat = $keyShopModFeat.'#'.$iSMF;
                        $newFeatures[$keyShopModFeat] = $shopModificationsFeature;
                    }
                }
            }
        }
        foreach ($newFeatures as $k=>$i) {  array_push($newFeatures,$i);  unset($newFeatures[$k]);  }

        if (count($newFeatures)>0) {
            DB::insertList('shop_modifications_feature', $newFeatures, TRUE);
        }

    }  // Записываем значения модификации

    private function createModImg($priceMods, $idsPrice)
    {
        /** Привязываем изображения к модификациям  <shop_modifications_img>
         * 1 получаем имяРисунка-ид (с фильтрацией по идТовара)
         * 2 заполняем массив на отправку в БД
         * 3 Чистим таблицу картинок модификаций перед записью
         * 4 записываем в <shop_modifications_img>
         */
        $this->debugging('funct', __FUNCTION__ . ' ' . __LINE__, __CLASS__, '[comment]');


        /** 1 получаем имяРисунка-ид (с фильтрацией по идТовара) */


        $idsProductsSrt = implode(",", $idsPrice);
        $u = new DB("shop_img", "si");
        $u->select("si.id, si.picture, sp.code");
        $u->where("si.id_price IN ($idsProductsSrt)");
        $u->innerjoin('shop_price sp', 'sp.id = si.id_price');
        $l = $u->getList();
        $list = array();
        foreach ($l as $k => $i) {
            $key = $i['code'].'##'.$i['picture'];
            $list[$key] = $i['id'];
            unset($l[$k]);
        }
        unset($u,$l,$key,$idsProductsSrt,$idsProducts);


        /** 2 заполняем массив на отправку в БД */

        $newImgs         = array();
        $idModifications = array();
        foreach ($priceMods as $priceKey=>$priceUnit) {
            foreach ($priceUnit as $key=>$val) {
                $mod = $priceMods[$priceKey][$key];

                if ($mod['img_alt'] != '') {
                    $imgs = $mod['img_alt'];
                    is_array($imgs) ?: $imgs=array($imgs);


                    /** заполняем форму на отправку */

                    $numSort = 0;
                    foreach ($imgs as $k => $i) {
                        $shopModificationsImg = array('id_modification' => $mod['idModification'],
                                                      'id_img'          => $list[$priceKey.'##'.$i],
                                                      'sort'            => $numSort);
                        if (!empty($mod['idModification']) and !empty($list[$priceKey.'##'.$i]) ) {
                            array_push($newImgs, $shopModificationsImg);
                            $numSort = $numSort + 1;
                        }
                    }
                    array_push($idModifications, $mod['idModification']);
                }
            }
        }


        /** 3 Чистим таблицу картинок модификаций перед записью */

        $idModificationsStr = implode(",", $idModifications);
        if ($idModificationsStr!='') {
            $u = new DB('shop_modifications_img', 'smi');
            $u->where('id_modification IN (?)', $idModificationsStr)->deletelist();
        }

        /** 4 записываем в <shop_modifications_img> */

        if (count($newImgs)>0) {
            DB::insertList('shop_modifications_img', $newImgs, TRUE);
        }

    } // Привязываем изображения к модификациям

    private function getRightData($item, $key)
    {
        /** Получить правильные данные
         *
         *
         * @@@@@@ @@@@@@ @@@@@@@@    @@@@@@ @@@@@@ @@@@@@    @@@@@@     @@    @@@@@@@@    @@
         * @@     @@        @@       @@  @@   @@   @@        @@   @@   @@@@      @@      @@@@
         * @@     @@@@@@    @@       @@@@@@   @@   @@        @@   @@  @@  @@     @@     @@  @@
         * @@  @@ @@        @@       @@ @@    @@   @@  @@    @@   @@ @@@@@@@@    @@    @@@@@@@@
         * @@@@@@ @@@@@@    @@       @@  @@ @@@@@@ @@@@@@    @@@@@@  @@    @@    @@    @@    @@
         *
         *
         * 1 создаем группы и связи с ними товаров
         * 2 Добавляем меры (веса/объема)
         * 3 Добавляем сопутствующие товары
         * 4 ас.массив значений записи в БД
         * 5 проверка корректности значений и запись в массив
         * 6 обработчик значений/текста в Остатке
         * 7 Обрабатываем модификации (если есть)
         * 8 Cверяем наличие характеристик и значений
         * 9 устанновка значений по умолчанию при NULL (ЗАГЛУШКИ)
         * 10 получение списка изображений из ячеек Excel
         *
         * @param array $item данные по КАЖДОМУ товару
         * @param array $this->importData данные для таблиц
         */
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');

        // // Добавляем категории
        // $this->addCategory(
        //     $this->get('id_group', "/,(?!\s+)/ui", $item),
        //     $this->get('code_group', "/,(?!\s+)/ui", $item),
        //     $this->get('path_group', "/,(?!\s+)/ui", $item)
        // );

        /** получаем код для использ. в качестве ключ. поля в скрипте */
        $code = $this->get('code', FALSE, $item);

        /** 1 создаем группы и связи с ними товаров */
        $id_gr = $this->CommunGroup($item, TRUE);

        /** 2 Добавляем меры (веса/объема) */
        $this->importData['measure'][$code] = array(
            'id_price' =>           $this->get('id', "/,(?!\s+)/ui", $item),
            "id_weight_view" =>     $this->getId('measures_weight', "/,(?!\s+)/ui", 'shop_measure_weight', 'name', $item)[0], /** НЕ ПРЕВОДИТЬ В int >> не отфильтровывается значеине при передаче */
            "id_weight_edit" =>     $this->getId('measures_weight', "/,(?!\s+)/ui", 'shop_measure_weight', 'name', $item)[1],
            "id_volume_view" =>     $this->getId('measures_volume', "/,(?!\s+)/ui", 'shop_measure_volume', 'name', $item)[0],
            "id_volume_edit" =>     $this->getId('measures_volume', "/,(?!\s+)/ui", 'shop_measure_volume', 'name', $item)[1]
        );

        /** 3 Добавляем сопутствующие товары */
        $accomp       = $this->get('code_acc', "/,(?!\s+)/ui", $item);
        $code_price_acc = $this->get('code', "/,(?!\s+)/ui", $item);
        /** если присутствуют сопутствующие - добавляем */
        if($accomp != NULL) {
            $accomp = is_array($accomp) ? $accomp : array($accomp);
            foreach ($accomp as $ac) {
                $this->importData['accomp'][$code][] = array(
                    'id_price' => $code_price_acc,
                    'code_acc'   => $ac
                );
            }
        };

        /** 4 ас.массив значений записи в БД */
        $Product = array(
            'id'             => NULL,
            'id_group'       => $id_gr,
            'name'           => $this->get('name', FALSE, $item),
            'article'        => $this->get('article', FALSE, $item),
            'code'           => $code,
            'price'          => $this->get('price', FALSE, $item),
            'price_opt'      => $this->get('price_opt', FALSE, $item),
            'price_opt_corp' => $this->get('price_opt_corp', FALSE, $item),
            'price_purchase' => $this->get('price_purchase', FALSE, $item),
            'bonus'          => $this->get('bonus', FALSE, $item),
            'presence_count' => $this->get('presence_count', FALSE, $item),
            'presence'       => NULL, /** если Остаток текстовый - поле заполняется ниже */
            'step_count'     => $this->get('step_count', FALSE, $item),

            'weight'         => $this->get('weight', FALSE, $item),
            'volume'         => $this->get('volume', FALSE, $item),

            'measure'        => $this->get('measure', "/,(?!\s+)/ui", $item),
            'note'           => $this->get('note', FALSE, $item),
            'text'           => $this->get('text', FALSE, $item),
            'curr'           => $this->get('curr', FALSE, $item),

            'enabled'        => $this->get('enabled', FALSE, $item),
            'flag_new'       => $this->get('flag_new', FALSE, $item),
            'flag_hit'       => $this->get('flag_hit', FALSE, $item),
            'is_market'      => $this->get('is_market', FALSE, $item),

            "img_alt"        => $this->get('img_alt', "/,(?!\s+)/ui", $item),
            'min_count'      => (int) $this->get('min_count', FALSE, $item),
            'id_brand'       => $this->getId('id_brand', FALSE, 'shop_brand', 'name', $item),

            'title'          => $this->get('title', FALSE, $item),
            'keywords'       => $this->get('keywords', FALSE, $item),
            'description'    => $this->get('description', FALSE, $item),
            'features'       => $this->get('features', FALSE, $item),

            'lineNum'        => $key+1 + $_SESSION['skip'] + $_SESSION['lastProcessedLine']  /** добавляем номер строки (с отступом и учетом цикла) */
            /** смотреть в БД */
        );

        /** 5 устанновка значений по умолчанию при NULL (ЗАГЛУШКИ) */
        $substitution = array(
            'curr'     => 'RUB',
            'enabled'  => 'Y',
            'flag_new' => 'N',
            'flag_hit' => 'N'
        );
        /** сверяем значения ячеек продукта со списком замены */
        foreach ($Product as $ingredient => &$include)
            foreach ($substitution as $ing => $inc)
                if ($ingredient == $ing and $include == NULL)
                    $Product[$ingredient] = $inc;

        /** 6 проверка корректности значений и запись в массив */
        $Product = $this->validationValues($Product, $code);

        /** 7 обработчик значений/текста в Остатке */
        if(!(int)$Product['presence_count'] and $Product['presence_count']!='0') {
            $Product['presence'] = $Product['presence_count'];
            $Product['presence_count'] = -1;
        }

        /** 8 Обрабатываем модификации (если есть) */
        $Product = $this->creationModificationsStart($Product, $item);

        /** 9 Cверяем наличие характеристик и значений */
        $Product = $this->creationFeature($Product, $item, $code);

        /**
         * НЕ ЖЕЛАТЕЛЬНО ИСПОЛЬЗОВАНИЕ ФИЛЬТРАЦИИ ПУСТЫХ ПОЛЕЙ В $Product !
         * ПРИВОДИТ К "ЗАЛИПАНИЮ" ЗНАЧЕНИЙ В БАЗЕ ДАННЫХ
         */

        // // фильтрация пустых полей в продукте
        // $Product = array();
        // foreach ($Product0 as $ingredient=>$include) {
        //     if($include !== NULL) {$Product[$ingredient]= $include;};
        // };

        /** 10 получение списка изображений из ячеек Excel */
        $imgList = array('img_alt','img', 'img_2', 'img_3', 'img_4', 'img_5', 'img_6', 'img_7', 'img_8', 'img_9', 'img_10');

        foreach ($imgList as $imgKey){
            if($imgLL = $this->get($imgKey, "/,(?!\s+)/ui", $item)){

                // разложение на элементы
                //$imgLL = iconv('utf-8', 'windows-1251', $imgLL);
                //$imgLL = explode("/,(?!\s+)/ui", $imgLL);

                /** преобразование информации по изображения под таблицу shop_img */
                is_array($imgLL) ?: $imgLL=array($imgLL);
                foreach ($imgLL as $result) {
                    $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');

                    if ($result != '') {

                        $newImg = array(
                            "id_price" => $code,
                            "picture"  => $result,
                            'lineNum'  => $Product['lineNum']
                        );

                        /** главное изображение */
                        if (($result == $imgLL[0] and $imgKey == 'img_alt') or $imgKey == 'img') {
                            $newImg["default"] = 0; /** было 1, но тк могут быть модификации (и соответственно несколько первых изображений) */
                        } else $newImg["default"] = 0;
                        $this->importData['img'][$code][] = $newImg;
                    }
                }
            }
        }

        unset($item);
    } // ПОЛУЧ. ПРАВИЛЬНЫЕ ДАННЫЕ

    private function validationValues($Product, $code)
    {
        /** проверка корректности значений и запись в массив
         * @return array $this->importData отправка товаров на сохранение
         * @return array $Product отправка данных для дальнейшей обработки
         */

        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');

        $line = $Product['lineNum']; /** номер строки (с учетом циклов) */
        $id = $Product['id'];

        $Product = $this->notText($Product, 'price', $line, "Цена пр.",'float', true);
        $Product = $this->notText($Product, 'price_opt', $line, "Цена опт.",'float', true);
        $Product = $this->notText($Product, 'price_opt_corp', $line, "Цена корп.",'float', true);
        $Product = $this->notText($Product, 'price_purchase', $line, "Цена закуп.",'float', true);
        $Product = $this->notText($Product, 'bonus', $line, "Цена бал.",'float', true);
        $Product = $this->bool($Product, 'is_market', $line, "Маркет",true, false);
        $Product = $this->bool($Product, 'enabled', $line, "Видимость",false, false);
        $Product = $this->bool($Product, 'flag_new', $line, "Новинки",false, false);
        $Product = $this->bool($Product, 'flag_hit', $line, "Хиты",false, false);
        $Product = $this->notText($Product, 'step_count', $line, 'Шаг количества','int', false);
        $Product = $this->notText($Product, 'weight', $line, 'Вес','float', true);
        $Product = $this->notText($Product, 'volume', $line, 'Объем','float', true);
        $Product = $this->notText($Product, 'min_count', $line, 'Мин.кол-во','int', true);

        // при пустом поле значение переменной $id==NULL
        if (!empty($Product['code']) and !empty($Product['name'])){
            $this->importData['products'][] = $Product;

        } else if (!empty($Product['code']) and empty($Product['name'])) {
            /** если имя пустое - подставить код (URL) */
            $Product['name'] = $Product['code'];
            if (!$_SESSION['errors']['name'])
                $_SESSION['errors']['name'] = 'ПРИМЕЧАНИЕ[стр. '.$line.']: столбец "Наименование" не может быть пустым. Произведена подстановка кода (URL)';

            $this->importData['products'][] = $Product;

        } else {
            if (empty($Product['code']) and !$_SESSION['errors']['code'])
                $_SESSION['errors']['code'] = 'ОШИБКА[стр. '.$line.']: столбец "Код (URL)" не может быть пустым';

        }

        return $Product;

    } // ПРОВЕРКА КОРРЕКТНОСТИ ЗНАЧЕНИЙ

    private static function notText($data, $col, $line, $name, $iF, $zero)
    {
        /**
         * проверка числового не отрицательного (большего, чем ноль) значения; в случае не совпадения - замена на значения
         * @param array  $data данные по товару
         * @param string $col  обозначение колонки в БД
         * @param int    $line номер строки
         * @param string $name обозначение колонки в файле
         * @param string $iF   новый формат значения: int / float
         * @param bool   $zero при ошибке приравнять к: true-0 false-1
         * @param int    $subs замена
         * @param string $text текст-подстановка
         */

        $v = $data[$col];
        if ($zero==true) { $subs=0; $text='значения меньшие чем ноль';}         /** приравнять к нулю */
        else             { $subs=1; $text='значения меньшие или равные нулю';}  /** приравнять к одному */

        if ($zero?
            (!(int)$v and $v!='0' and !empty($v) or (float)$v<0) :
            (!(int)$v and $v!='0' and !empty($v) or (float)$v<0 or $v=='0')
        ) {
            if (!$_SESSION['errors'][$col]) $_SESSION['errors'][$col]='ПРИМЕЧАНИЕ[стр. '.$line.']: столбец "'.$name.'" не может содержать текст и '.$text.'. Произведена замена на '.$subs;
            $v = $subs;
        } elseif (empty($v)) $v = $subs;

        if ($iF=='int')       $data[$col] = (int)$v;
        elseif ($iF=='float') $data[$col] = round((float)$v, 2);

        return $data;
    } // проверка числового не отрицательного / большего, чем ноль

    private static function bool($data, $col, $line, $name, $oneYes, $yesNo)
    {
        /**
         * проверка логического значения; в случае не совпадения - замена на значения
         * @param array  $data    данные по товару
         * @param string $col     обозначение колонки в БД
         * @param int    $line    номер строки
         * @param string $name    обозначение колонки в файле
         * @param bool   $oneYes  новый формат значения: true-0/1 или false-Y/N
         * @param bool   $yesNo   при ошибке приравнять к: true-Y/1 false-N/0
         * @param        $subs    замена, приравнять к ...
         * @param string $text    текст-подстановка
         */

        $v = $data[$col];

        if ($oneYes==true) {
            $text='0/1';
            if ($yesNo==true) $subs=1;
            else              $subs=0;
        } else {
            $text='Y/N';
            if ($yesNo==true) $subs='Y';
            else              $subs='N';
        }

        if ($oneYes?
            ($v!='1' and $v!='0') :
            ($v!='Y' and $v!='N')
        ) {
            if (!$_SESSION['errors'][$col]) $_SESSION['errors'][$col]='ПРИМЕЧАНИЕ[стр. '.$line.']: столбец "'.$name.'" значение не равно '.$text.'. Произведена замена на '.$subs;
            $v = $subs;
        } elseif (empty($v)) $v = $subs;

        if     ($oneYes==true)   $data[$col] = (int)$v;
        elseif ($oneYes==false)  $data[$col] = $v;

        return $data;
    }

    private function addData()
    {

        /**
         * Добавить данные
         * @param  string mode                        "upd" - обновление, "ins" - вставка, "rld" - вставка с удалением
         * @method childTablesWentTokeys()            ставим ид в ключи массивов
         * @method array priceCodeId(array $products) сверка кодов с БД и получение idБД
         * @method updateListImport()                 импорт товаров - обновление (совпадения заменяет)
         * @method insertListImport()                 импорт товаров - вставка (совпадения игнорирует)
         */

        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');

        try{

            if (!empty($this->importData['products']))
                $this->importData['products'] = $this->priceCodeId($this->importData['products']);

            if (!empty($this->importData['products'])) {
                if     ($this->mode=='upd') $this->insertUpdateListImport(true);
                elseif ($this->mode=='ins' or $this->mode=='rld') $this->insertUpdateListImport(false);
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
    } // ДОБАВИТЬ ДАННЫЕ

    private function priceCodeId($products)
    {

        /**
         * получение ид - кодов по кодам товаров
         * @param  array  $products импортируемые данные для таблицы shop_price
         * @param  string $codes    коды импортируемых товаров, через запятую
         * @return array  $codeId   коды-id товаров из базы
         * @return array  $products импортируемые данные для таблицы shop_price
         */

        $this->debugging('funct', __FUNCTION__ . ' ' . __LINE__, __CLASS__, '[comment]');

        $codes = array();
        foreach ($products as $k=>$i)
            $codes[$i['code']] = '"'.$i['code'].'"';
        $codes = implode(",", $codes);

        /** отправка запроса на получение id по кодам товаров */
        $u = new DB('shop_price', 'sp');
        $u->select('sp.id, sp.code');
        $u->where('sp.code in (?)', $codes);
        $arrayDB = $u->getList();
        $codeId  = array();
        foreach ($arrayDB as $k=>$i) $codeId[$i['code']] = $i['id'];

        /** подстановка полученных кодов, как newId */
        foreach ($products as $k=>$i)
            if ($codeId[$i['code']]) $products[$k]['newId'] = $codeId[$i['code']];

        return $products;

    } // получение ид - кодов по кодам товаров

    private function replacingIdСhildTables($oldId,$newId)
    {
        /**
         * замена id в дочерних таблицах и главной
         * @param  array $this->importData  импортируемые таблицы
         * @param  array $NID newImportData новый массив с новыми ids
         * @param  array $KTN kTableName    имя таблицы
         * @param  array $VTV valuesTV      значения таблицы
         * @return array $TID               импортируемые таблицы
         */

        $this->debugging('funct', __FUNCTION__ . ' ' . __LINE__, __CLASS__, '[comment]');

        $NID = array();

        foreach ($this->importData as $KTN => $tableValue) {
            if ($KTN != 'products') {

                foreach ($tableValue as $oldIdTV => $VTV) {
                    if ($oldIdTV == $oldId) {

                        if (array_key_exists('id_price', $VTV)) {
                            $VTV['id_price'] = $newId;

                        } else {
                            foreach ($VTV as $k => $i) {
                                if (array_key_exists('id_price', $i)) {
                                    $VTV[$k]['id_price'] = $newId;
                                }
                            }

                        }

                        $NID[$KTN][$newId] = $VTV;
                    }
                }
            }
        }

        return $NID;

    } // замена id в дочерних таблицах и главной

    private function deleteCategory($id_price,$id_group)
    {
        /** Удалить категорию */
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');
        $spg = new DB('shop_price_group','spg');
        $spg->select('spg.*');
        $spg->where('id_price = ?', $id_price);
        $spg->andWhere('id_group = ?', $id_group);
        $spg->deleteList();
    } // Удалить категорию

    public function insertUpdateListImport($update=false)
    {
        /** Лист продуктов на обновление (при отсутствии - добавление; при наличии - обновление (замена))
         * запись ПРОДУКТОВ и СВЯЗЕЙ группа-продукт
         *
         * 1: подмена id в существующих товарах
         * 2: начать транзакцию
         * 3: если id группы не получена (новая руппа) - получаем
         * 4: для shop_price id_group
         *   записываем ПРОДУКТ в бд (ГРУППЫ записаны при вызове CommunGroup в getRightData)
         *   если имеем дело с новой группой
         *   ... или соответственно массивом
         * 5: сверяем id товара с shop_price,    если нет > отправляем к инсету на добавление
         *   если товар отсутствует - создаем
         *   иначе просто изменяем
         *
         * 6: конец транзакции
         * 7: в случае ошибки - прервать транзакцию
         *
         * обновление: нетТовара-добавляет, есть-изменяет
         * вставка:    нетТовара-добавляет, есть-пропускает
         *
         * @param  bool  $update    true- updateListImport, false- insertListImport
         * @param  bool  $refresh   true- товар существует в БД
         * @param  bool  $processed true- товар обработан
         * @param  array $img       изображения по всем товарам
         * @method writeTempFileRelatedProducts(int) запись сопутств. товаров во времен. файл
         */

        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');

        $product_list = $this->importData['products'];

        try {

            $ids = array();
            $img = array();
            foreach ($product_list as $k => $product_unit){

                $line=$product_unit['lineNum']; unset($product_unit['lineNum']);   /** вынимаем номер строки из массива */
                $processed = false;

                /** 1 подмена id в существующих товарах */
                $code = $product_unit['code'];
                if ($product_unit['newId'] and !empty($product_unit['newId'])) {
                    $id_price = $product_unit['id'] = $product_unit['newId'];
                    unset($product_unit['newId']);
                    $refresh = true;
                }

                if ($product_unit['code'] != $_SESSION['lastCodePrice']) {

                    DB::beginTransaction();                                        /** 2 начать транзакцию */

                    if (gettype($product_unit['id_group']) == 'array' and          /** 3 если id группы не получена (новая руппа) - получаем */
                        gettype($product_unit['id_group'][0]) == 'array'
                    ) $id_group = $this->CommunGroup($product_unit['id_group'][0], FALSE);

                    $data_unit = $product_unit;                                    /** 4 */
                    if (gettype($product_unit['id_group']) == 'array' and
                        gettype($product_unit['id_group'][0]) == 'array'
                    ) $data_unit['id_group'] = $id_group[0];
                    elseif(gettype($product_unit['id_group']) == 'array')
                        $data_unit['id_group'] = $data_unit['id_group'][0];
                    $pr_unit = new DB('shop_price');

                    if($refresh==true and $update==true) {                         /** 5 */

                        /** если уже есть в БД  - обновляем */

                        $id_price = $data_unit['id'];
                        $this->productTables = $this->replacingIdСhildTables($code,$id_price);
                        foreach ($this->productTables['img'] as $k=>$i)
                            $img[] = $i;

                        $data_unut_sp = $data_unit;
                        unset($data_unut_sp[0]['features']);
                        $pr_unit->setValuesFields($data_unut_sp);
                        $id_price = $pr_unit->save(false);                         /** логические отвечают за обновление (при совпадении - замена) */
                        $product_unit['id'] = $id_price;
                        unset($data_unut_sp);

                        $this->insertListChildTablesAfterPrice($id_price, false);

                        if ($this->productTables['accomp'][$id_price])
                            $this->writeTempFileRelatedProducts($this->productTables['accomp'][$id_price]);

                        $this->linkRecordShopPriceGroup($product_unit,$id_group,$id_price);

                        array_push($id_list,$id_price);
                        $processed = true;

                    } else if ($refresh!=true) {

                        /** иначе создаем */
                        $data_unit['id'] = '';

                        $pr_unit->setValuesFields($data_unit);
                        $id_price = $pr_unit->save();
                        $product_unit['id'] = $id_price;
                        $this->productTables = $this->replacingIdСhildTables($code,$id_price);
                        foreach ($this->productTables['img'] as $k=>$i)
                            $img[] = $i;

                        $this->insertListChildTablesAfterPrice($id_price, true);

                        /** вынимаем номер строки из массива */
                        $line = $product_unit['lineNum'];
                        unset($product_unit['lineNum']);

                        if ($this->productTables['accomp'][$id_price])
                            $this->writeTempFileRelatedProducts($this->productTables['accomp'][$id_price]);

                        $this->linkRecordShopPriceGroup($product_unit, $id_groups, $id_price);

                        $processed = true;
                    };

                    if ($processed == true) {
                        $_SESSION['lastCodePrice'] = $product_unit['code'];
                        $_SESSION['lastIdPrice']    = $id_price;
                    }

                    DB::commit();                                                  /** 6 конец транзакции */

                } else {

                    $id_price = $_SESSION['lastIdPrice'];

                    /** ДЛЯ МОДИФИКАЦИЙ: даже если не пишем товар - записать картинки для привязки к модификациям */
                    $this->productTables = $this->replacingIdСhildTables($code,$id_price);
                    foreach ($this->productTables['img'] as $k=>$i)
                        $img[] = $i;

                };

                if ($processed==true)  $ids[$code]=$id_price;
                $product_list[$k]=$product_unit;
            };

            if (count($ids) > 0) {
                $this->checkCreateImg($ids, $img);
                $this->creationModificationsFinal($ids);
            }

        } catch (Exception $e) {
            DB::rollBack();                                                        /** 7 в случае ошибки - прервать транзакцию */
            return false;
        };
        return true;
    } // обновление: нет товара-добавление, есть - обновление (замена)

    private static function checkCreateImg($ids,$faileImgs)
    {
        /** Проверить есть ли изображения у товара - если нет, создать
         * @param array $faileImgs           данные по изображениям  [<id>]=>{[0..]=>(id_price, picture, default)}
         * @param num   $ids                 ид товаров к которым привязаны изображения
         * @param bool  $isFile              true - файл корректен  false - не корректен
         * @param array $exten               возможные расширения файлов
         */


        /** 1 получаем Корректные изображ товаров из файла, приводим к нум-массиву */
        $Imgs = array();
        foreach ($faileImgs as $k=>$i)
            foreach ($i as $k2=>$i2) {

                $exten=array('.jpg','.jpeg','.jpe','.png','.tif','.tiff','.gif','.bmp','.dib','.psd');
                $isFile=false;
                foreach ($exten as $kEX=>$iEX)
                    if (strpos(' '.$i2['picture'],$iEX)) {
                        $isFile = true;
                        break;
                    }

                if ($isFile) {
                    unset($i2['lineNum']);
                    if (!empty($i2)) array_push($Imgs, $i2);
                } elseif (count($Imgs)!=0 and $i['picture']!='')
                    if (!$_SESSION['errors']['img_alt']) $_SESSION['errors']['img_alt']='ПРИМЕЧАНИЕ[стр. '.$i2['lineNum'].']: столбец "Изображения" не корректное расширение файла';
            }


        /** 2 получаем изображения товара из БД */
        $ids = implode(",", $ids);
        $u = new DB("shop_img", "si");
        $u->select("si.id, si.picture, si.id_price");
        $u->where('si.id_price in (?)', $ids);
        $l = $u->getList();
        $list = array();
        foreach ($l as $k => $i) {
            $key = $i['idPrice']."##".$i['picture'];
            $list[$key] = $i['id'];
            unset($l[$k]);
        }
        unset($u,$l);


        /** 3 отбираем отсутсвующие рисунки */
        if (!$Imgs[0] and isset($Imgs)) $Imgs=array($Imgs);
        $faileImgsTemp = array();
        foreach ($Imgs as $k=>$i) {
            $key  = $i['idPrice']."##".$i['picture'];
            if (!$list[$key] and !empty($i))
                array_push($faileImgsTemp, $i);
            unset($Imgs[$k]);
        }
        $Imgs = $faileImgsTemp; unset($faileImgsTemp);


        /** 4 определяем главное изображение и возвращаем на запись */
        if (count($list)==0 and $Imgs[0]) $Imgs[0]["default"]=1;

        if (count($Imgs)>0)
            DB::insertList("shop_img", $Imgs,true);

    } // Проверить есть ли изображения у товара - если нет, создать

    private function insertListChildTablesAfterPrice($id, $setValuesFields = false)
    {
        /** Заполнение дочерних таблиц После заполнения <shop_price>    1 импорт характеристик    2 импорт изображений*/
        $tableNames = array(
            'category' =>array('table'=>'shop_group'),
            'measure'  =>array('table'=>'shop_price_measure'),
            'features' =>array(
                'table'     =>'shop_modifications_feature',
                'revise'    =>'id_price, id_modification, id_feature,id_value',
                'keyRevise' =>'id_price',
                'empty'     =>'id_modification' // проверка пустоту поля
            )
        );
        $this->insertListChildTables($id, $tableNames, $setValuesFields);
    } // Заполнение дочерних таблиц После заполнения <shop_price>

    private function insertListChildTables($id, $tableNames, $setValuesFields=false)
    {
        /** Форма заполнения дочерних таблиц
         * @param array $this->productTables именной массив данных из строки (разбитые по таблицам ключТабл) по одному товару
         * @param int   $id                  ид товара к которому привязаны записи таблиц
         * @param bool  $setValuesFields     true=изменяемЗапись  false=создаем
         * @param array $tableNames          ключТабл=> table=имяТабл, revise=поляСверки, keyRevise=id_price, empty=проверкаПустоты
         * @param array $tableData           массив данных для записи в БД
         * @param str   $tab                 имя таблицы
         */

        /** id в ключи в массива <measure,features,prepare...> */
        $keyTable = array_keys($this->productTables);

        foreach ($keyTable as $k=>$i) {

            if ($tableNames[$i]['table'] and $this->productTables[$i][$id]) {

                $tableData = $this->productTables[$i][$id];
                $tableData[0] ?: $tableData=array($tableData);

                $shop_price = new DB($tableNames[$i]['table']);
                $shop_price->select('id_price');
                $id_list = $shop_price->getList();
                foreach ($id_list as $k2=>$i2) {
                    $id_list[$i2['idPrice']]=true;
                    unset($id_list[$k2]);
                }

                /** проверка не пустой строки */
                $tableDataTemp = array();
                foreach ($tableData as $k2=>$i2) {
                    foreach ($i2 as $k3 => $i3) {
                        if ($k3 != 'id_price' and $i3 != '') {
                            array_push($tableDataTemp, $i2);
                            break;
                        };
                    }
                }
                if (count($tableDataTemp)>0) {

                    /** фильтрация уникальных значений */

                    $tableData[0] ?: $tableData=array($tableData);

                    foreach ($tableData as $kTD=>$iTD) {
                        $keyTDUnit = '';
                        foreach ($iTD as $kTDU=>$iTDU)  $keyTDUnit=$keyTDUnit.'#'.$iTDU;
                        $tableData[$keyTDUnit]=$iTD;    unset($tableData[$kTD]);
                    }
                    foreach ($tableData as $kTD=>$iTD) {array_push($tableData,$iTD);  unset($tableData[$kTD]);}
                    $tableData = array_values($tableData);

                    if ($setValuesFields==false) {

                        $tableData[0] ?: $tableData=array($tableData);

                        /** фильтрация совпадений записей с базой */

                        if ($tableNames[$i]['keyRevise']) {

                            // получаем записи по id из DB
                            $revise = new DB($tableNames[$i]['table']);
                            $revise->select($tableNames[$i]['revise']);
                            $revise->where($tableNames[$i]['keyRevise']." = ?", $tableData[0]['id_price']);
                            $reviseList = $revise->getList();

                            // ключи к змеиному регистру
                            $snakeRegister = array();
                            foreach($reviseList as $k2=>$i2) {
                                $unit = array();
                                foreach ($i2 as $k3=>$i3) {
                                    $k3 = DB::strToUnderscore($k3);
                                    $unit[$k3] = $i3;
                                }
                                $snakeRegister[$k2] = $unit;
                            }
                            $reviseList = $snakeRegister;
                            unset($snakeRegister);

                            // если есть условие на пустое поле - проверяем
                            if ($tableNames[$i]['empty']) {
                                $filter = array();
                                foreach ($reviseList as $item)
                                    if(empty($item[$tableNames[$i]['empty']]))
                                        $filter[] = $item;

                                $reviseList = $filter;
                                unset($filter);
                            }

                            // проводим сверку DB с импортом
                            foreach ($tableData as $importKey => $importItem) {
                                foreach ($reviseList as $dbKey => $dbItem) {

                                    $sharedKeys = array_intersect_key($importItem,$dbItem);
                                    $dbItem     = array_intersect_key($dbItem, $importItem);
                                    $sharedItem = array_uintersect($sharedKeys, $dbItem, "strcasecmp");

                                    if (count($sharedItem) == count($dbItem))
                                        unset($tableData[$importKey]);
                                }
                            }
                        }

                        DB::insertList($tableNames[$i]['table'], $tableData,true);
                    } else {
                        /** удаляем старую запись*/
                        if ($id_list[$id]) {
                            DB::query("SET foreign_key_checks = 0");
                            $tab=$tableNames[$i]['table'];
                            $u = new DB($tab, $tab);
                            $u->where($tab.".id_price IN ($id)")->deleteList();
                            DB::query("SET foreign_key_checks = 1");
                        }
                        //$tableDB = new DB($tableNames[$i]['table']);
                        //$tableDB->setValuesFields($tableData);
                        //$idDB = $tableDB->save();
                        $tableData[0] ? $tableData=$tableData : $tableData=array($tableData);
                        DB::insertList($tableNames[$i]['table'], $tableData,true);
                    }
                }
            }
        }
    } // Форма заполнения дочерних таблиц

    public function linkRecordShopPriceGroup($product_unit,$id_group,$id_price)
    {
        /**
         * ЗАПОЛНЕНИЕ shop_price_group
         *
         * Определяем потребность в очистке связей и удаляем не актуальные
         *
         * 1: если значение одно - завернуть в массив для обработки
         *   если значения не соотнесены (отсутствовали данные по id) - совершить вторую попытку
         * 2: получаем данные из базы для определения изменений
         * 3: раскладываем продукт на связи (с группами)
         *   4: формируем данные по импортируемой связи;  определяем главную/второстепенные связи
         *   5: группируем параметры связи
         *   6: сопоставляем параметры с бд
         *   7: если есть хотя бы одно совпадение по бд - отменяем
         * 8: сверка связей с созданым белым листом - в случае не обнаружения среди белых, УДАЛЯЕТ СВЯЗЬ
         *
         * Запись связей в таблицу shop_price_group
         *
         * 9: получаем данные из shop_price_group для последующей сверки данны с id-шниками (проверка на наличие)
         *   ОБЯЗАТЕЛЬНО ПОСЛЕ ОЧИСТКИ ТАБЛИЦЫ!
         * 10: получение элементов массива с определением главной группы
         *   11: если группа первая в списке - значит главная
         *   12: ищим id в базе - есть, добавляем в shop_price_group
         *
         * @param array $product_unit  массив параметров $fields по единице товара
         * @param integer $id_group    ид группы
         * @param integer $id_price    ид товара
         */
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');

        if(gettype($product_unit['id_group']) == integer) {
            $product_unit['id_group'] = array($product_unit['id_group']);                  /** 1 */
        } elseif(gettype($product_unit['id_group']) == 'array' and gettype($product_unit['id_group'][0]) == 'array') {
            $product_unit['id_group'] = $id_group;
        }

        if(($_SESSION['coreVersion'] > 520) and is_numeric($id_price) and isset($product_unit['id_group'])){

            $pr_gr = new DB('shop_price_group');                                           /** 2 */
            $pr_gr->select('*');
            $pr_gr->where('id_price = ?', $id_price);
            $pr_gr_list = $pr_gr->getList();


            if($pr_gr_list != NULL) {
                $white_list = array();
                $cycle = 0;
                foreach ($product_unit['id_group'] as $id_gr_unit) {                       /** 3 */
                    if ($cycle == 0) $is_main = (int)1;                                    /** 4 */
                    else             $is_main = (int)0;
                    $category_unit = array(                                                /** 5 */
                        'id' => NULL,
                        'idPrice' => (int)$id_price,
                        'idGroup' => (int)$id_gr_unit,
                        'isMain' => (bool)$is_main
                    );
                    foreach ($pr_gr_list as $pr_gr_unit) {                                 /** 6 */
                        if ($category_unit['idPrice'] == $pr_gr_unit['idPrice'] and
                            $category_unit['idGroup'] == $pr_gr_unit['idGroup'] and
                            $category_unit['isMain'] == $pr_gr_unit['isMain']
                        ) $category_unit['id'] = $pr_gr_unit['id'];
                    };
                    if ($category_unit['id'] != NULL) $white_list[] = $category_unit;      /** 7 */

                    $cycle = $cycle + 1;
                };

                $delete_id_list = array();                                                 /** 8 */
                foreach ($pr_gr_list as $pr_gr_unit) {
                    if ($pr_gr_unit['idPrice'] == $id_price) {
                        $delete_confirmation = (int)1;
                        foreach ($white_list as $white_unit) {
                            if ($white_unit['id'] == $pr_gr_unit['id']) $delete_confirmation = (int)0;
                        };
                        if ($delete_confirmation == 1) $delete_id_list[] = $pr_gr_unit['id'];
                    };
                };
                if(count($delete_id_list) > 0) {
                    $spg = new DB('shop_price_group', 'spg');
                    $spg->select('spg.*');
                    $spg->where('id IN (?)', join(',', $delete_id_list));
                    $spg->deleteList();
                };
            };

            $pr_gr_list_delete = array();                                                  /** 9 */
            foreach($pr_gr_list as &$pr_gr_unit) {
                if(in_array($pr_gr_unit['id'], $delete_id_list) == FALSE) $pr_gr_list_delete[] = $pr_gr_unit;
            };
            $pr_gr_list = $pr_gr_list_delete;

            if(isset($product_unit['id'],$product_unit['id_group'][0])) {                  /** 10 */
                foreach ($product_unit['id_group'] as $id_gr_unit) {
                    if(isset($product_unit['id'],$id_gr_unit)) {
                        $category_unit = array(
                            'id'      => NULL,
                            'idPrice' => (int) $id_price,
                            'idGroup' => (int) $id_gr_unit,
                            'isMain'  => (bool) 0
                        );
                        if($id_gr_unit == $product_unit['id_group'][0])                    /** 11 */
                            $category_unit['isMain'] = (bool) 1;
                        if($pr_gr_list != NULL) {
                            foreach($pr_gr_list as $pr_gr_unit){                           /** 12 */
                                if( $category_unit['idPrice'] == $pr_gr_unit['idPrice'] and
                                    $category_unit['idGroup'] == $pr_gr_unit['idGroup'] and
                                    $category_unit['isMain']  == $pr_gr_unit['isMain']
                                )   $category_unit['id'] = $pr_gr_unit['id'];
                            };
                        };
                        $pr_gr = new DB('shop_price_group');
                        $pr_gr->setValuesFields($category_unit);
                        $pr_gr->save();
                    };
                };
            };
        };
    } // ЗАПОЛНЕНИЕ shop_price_group

    private function writeTempFileRelatedProducts($relatedProducts)
    {
        /**
         * запись сопутствующих товаров в конец временного файла
         *
         * @param array  $relatedProducts массив сопутств. товаров по обрабат. товару
         * @param string $path            путь времен. файла
         * @param string $idCode          связка <id создан. товара>,<code сопутств. товара>
         */

        $this->debugging('funct', __FUNCTION__ . ' ' . __LINE__, __CLASS__, '[comment]');

        foreach($relatedProducts as $k=>$i) {
            $idCode = $i['id_price'] . "," . $i['code_acc'] . "\n";
            if (!empty($idCode)) {
                $path = DOCUMENT_ROOT . "/files/tempfiles/relatedProducts.TMP";
                file_put_contents($path, $idCode, FILE_APPEND);
            }
        }
    } // запись сопутств. тов. в конец времен. файла

    private function inserRelatedProducts()
    {
        /**
         * запись сопутствующих товаров в БД
         *
         * @param string $path     путь времен. файла
         * @param string $line     строка из файла
         * @param string $idCode   связка <id создан. товара>,<code сопутств. товара>
         * @param array  $DBunit   форма отправки данных в БД
         * @param array  $arrayDB  список на отправку insertList
         * @param array  $idDelete список ids товар. на очистку табл. сопутств. тов. (shop_accomp)
         */

        $this->debugging('funct', __FUNCTION__ . ' ' . __LINE__, __CLASS__, '[comment]');

        $path = @fopen(DOCUMENT_ROOT . "/files/tempfiles/relatedProducts.TMP", "r");
        if ($path) {
            $arrayDB  = array();
            $idDelete = array();
            while (($line = fgets($path, 10000000)) !== false) {

                $line = substr($line, 0, -1);

                /** если строчка не пустая - упаков. и помещ. в список insertList */
                if(gettype($line) == string) {
                    $idCode      = preg_split("/,(?!\s+)/ui", $line);
                    $id          = $idCode[0];
                    $codeRelated = $idCode[1];
                    $DBunit = array('id_price'=>$id,'id_acc'=>$codeRelated);
                    array_push($arrayDB,$DBunit);
                    array_push($idDelete,$id);
                }

                /** когда накапливается 1000 - записываем в БД, обнул. массив */
                if (count($arrayDB) >= 1000) {
                    $idsStr = implode(",", $idDelete);
                    $u = new DB('shop_accomp', 'sa');
                    $u->where("id_price IN (?)", $idsStr)->deleteList();

                    $arrayDB = $this->codesInIdRelated($arrayDB);

                    DB::insertList('shop_accomp', $arrayDB);
                    $arrayDB  = array();
                    $idDelete = array();
                }
            }

            if (count($arrayDB) > 0) {
                $idsStr = implode(",", $idDelete);
                $u = new DB('shop_accomp', 'sa');
                $u->where("id_price IN (?)", $idsStr)->deleteList();

                $arrayDB = $this->codesInIdRelated($arrayDB);

                DB::insertList('shop_accomp', $arrayDB);
            }

            if (!feof($path)) {
                // echo "Ошибка: fgets() неожиданно потерпел неудачу\n";
            }

            fclose($path);
        }

    } // запись сопутств. тов. в БД

    private static function codesInIdRelated($arrayDB)
    {
        /**
         * замена кодов сопустствующих товаров на ид
         * @param  array $arrayDB  массив сопутствующих товаров [0][id_price,id_acc] (id_acc - коды)
         * @param  array $codes    массив кодов сопутствующих товаров
         * @param  str   $codesStr массив кодов сопутствующих товаров в Строку
         * @param  array $codeId   связки код-ид по товарам
         * @return array $arrayDB  массив сопутствующих товаров [0][id_price,id_acc] (id_acc - ид)
         */

        $codes = array();
        foreach ($arrayDB as $k=>$i)
            $codes[$i['id_acc']] = true;
        $codes = array_keys($codes);

        $codesStr = '';
        foreach ($codes as $k=>$i) $codesStr .= "'$i',";
        $codesStr = substr($codesStr, 0, -1);

        if (!empty($codesStr)) {
            $u = new DB('shop_price', 'sp');
            $u->select('sp.id, sp.code');
            $u->where("sp.code IN ($codesStr)");
            $list = $u->getList();
            unset($u,$codes);
        } else $list = array();

        $codeId = array();
        foreach($list as $k=>$i)
            $codeId[$i['code']] = $i['id'];

        foreach ($arrayDB as $k=>$i) {
            if ($codeId[$i['id_acc']])
                $arrayDB[$k]['id_acc'] = $codeId[$i['id_acc']];
            else
                unset($arrayDB[$k]);
        }
        $arrayDB = array_values($arrayDB);
        unset($codeId);

        return $arrayDB;
    } // замена кодов сопустствующих товаров на ид

    public function updateGroupTable()
    {
        /** Обновить Группы таблиц */
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');

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
        DB::insertList('shop_group_tree', $data);

    } // Обновить Группы таблиц

    private function addInTree($tree , $parent = 0, $level = 0, &$treepath = array())
    {
        /** добавить в Дерево */
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');

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
    } // добавить в Дерево

}
