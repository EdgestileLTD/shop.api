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
        'id_acc'            => "Сопутствующие товары",

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

    public function startImport($filename, $prepare = false, $options, $customEdition, $cycleNum)
    {
        /** Запуск импорта
         *
         * @@@@@@ @@@@@@@@    @@    @@@@@@ @@@@@@@@    @@@@@@ @@     @@ @@@@@@ @@@@@@ @@@@@@ @@@@@@@@
         * @@        @@      @@@@   @@  @@    @@         @@   @@@   @@@ @@  @@ @@  @@ @@  @@    @@
         * @@@@@@    @@     @@  @@  @@@@@@    @@         @@   @@ @@@ @@ @@@@@@ @@  @@ @@@@@@    @@
         *     @@    @@    @@@@@@@@ @@ @@     @@         @@   @@  @  @@ @@     @@  @@ @@ @@     @@
         * @@@@@@    @@    @@    @@ @@  @@    @@       @@@@@@ @@     @@ @@     @@@@@@ @@  @@    @@
         *
         * if     - превью      - чтение первых 100 строк
         * elseif - первый цикл - весь файл во временные
         *
         * @param int   $_SESSION['lastProcessedLine'] последняя обработанная строка (без превью)
         * @param array $_SESSION['errors']            данные по некритическим ошибкам, возникшим в ходе обработки импФайла
         * @method bool public $this->getDataFromFile($filename, $options, $prepare) чтение и разложение файла на временные
         */
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');

        $this->cycleNum = $cycleNum;
        if ($prepare) {
            /** превью - обнуление переменных сессии, чтение файла */
            unset($_SESSION["cycleNum"]);
            unset($_SESSION["pages"]);
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
                    DB::query('SET foreign_key_checks = 0');
                    DB::insertList('shop_group', array($newCat),TRUE);
                    DB::query('SET foreign_key_checks = 1');
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

        foreach ($array as $key => $field) {
            if ($rusFields[$field]) {
                $name = $rusFields[$field];
                $this->fieldsMap[$name] = $key;
            } elseif (preg_match("/[^\s]+#(?!\s+)/ui",$field)) {
                /** 3 проверка на модификацию и ее корректность*/
                $amountElements = explode('#',$field);
                if (count($amountElements)==2 and $amountElements[0]!='' and $amountElements[1]!='') {
                    $this->fieldsMap[$field] = $key;
                } elseif (count($amountElements)>2) {
                    $nameNum = $this->getNameFromNumber($key);
                    $_SESSION['errors']['headline'] = "ОШИБКА[стлб. ".$nameNum."]: Ошибка заголовка столбца модификации!";
                    //throw new Exception($this->error);
                } else {}
            }
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
                    if ($this->rowCheck($item) == TRUE)  $this->getRightData($item, $options, $key);
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
                            $_SESSION["getId"][$key][$lObj] = (int)$object[id]; /** запоминаем связку в сессии (для быстродействия) */
                        }
                    }
                }
                array_push($get, $code);
            }

            if (count($get) == 1)
                $get = $get[0];

            return $get;

        } else
            return NULL;
    } // Получение ИД от Имени/Кода...

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

    private function creationFeature($Product, $item)
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
        $this->importData['features'] = $insertListFeatures;
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
                        DB::query('SET foreign_key_checks = 0');
                        DB::insertList('shop_feature', array($newfeat),TRUE);
                        DB::query('SET foreign_key_checks = 1');

                        $u = new DB("shop_feature", "sf");
                        $u->select("sf.id");
                        $u->where("sf.name = '$value'");
                        $list = $u->getList();
                        unset($u);

                        $id = $list[0]['id'];
                        $this->feature[$section][$id] = $features[$value];
                        $features[$id] = $features[$value];
                    } elseif ($newfeat['name'] or $newfeat['type']) {
                        /** вывод ошибки с линией */
                        $line = $lineNum;
                        if (!$_SESSION['errors']['feature']) $_SESSION['errors']['feature'] = 'ОШИБКА[стр. '.$line.']: не корректное заполнение столбца "Характеристики"';
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
                        if ($newfeat['name'] and $newfeat['type']) {
                            DB::query('SET foreign_key_checks = 0');
                            DB::insertList('shop_feature_value_list', array($newfeat),TRUE);
                            DB::query('SET foreign_key_checks = 1');

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
                            if (!$_SESSION['errors']['feature']) $_SESSION['errors']['feature'] = 'ОШИБКА[стр. '.$line.']: не корректное заполнение столбца "Характеристики"';
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
            $Product['features'] = ""; /** заглушка для характеристик */
            $this->modData[$Product['id']][]=$mod;
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

        $priceMods = array(); /** с сортировкой по товарам */
        foreach($idsPrice as $k=>$i)
            if ($i == true) $priceMods[$k] = $this->modData[$k];
        unset($idsPrice);


        if (count($priceMods)>0) {

            /** 2 создаем группы модификаций, модификации, параметры, соединяем (при их отсутствии) */

            $priceMods = $this->createModifGroup($priceMods);  /** shop_modifications_group */
            $priceMods = $this->createFeature($priceMods);     /** shop_feature */
            $priceMods = $this->createModifValue($priceMods);  /** shop_feature_value_list */
            $this->createCommunMod($priceMods);                /** shop_group_feature */


            /** 3 Преобразуем данные к записи в БД */

            $priceMods = $this->checkCreatMod($priceMods);
            $priceMods = $this->createModifications($priceMods); /** заполнение shop_modifications */
            $this->createModFeature($priceMods);                 /** заполнение shop_modifications_feature */
            $this->createModImg($priceMods);                     /** заполнение shop_modifications_img */
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
            DB::query('SET foreign_key_checks = 0');
            DB::insertList('shop_modifications_group', $newModGroups,TRUE);
            DB::query('SET foreign_key_checks = 1');
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
            DB::query('SET foreign_key_checks = 0');
            DB::insertList('shop_feature', $newMods, TRUE);
            DB::query('SET foreign_key_checks = 1');
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
            DB::query('SET foreign_key_checks = 0');
            DB::insertList('shop_feature_value_list', $newModValues, TRUE);
            DB::query('SET foreign_key_checks = 1');
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
            DB::query('SET foreign_key_checks = 0');
            DB::insertList('shop_group_feature', $newLigaments, TRUE);
            DB::query('SET foreign_key_checks = 1');
            unset($this->feature['sgf']);
            //$this->getFeatureInquiry("shop_group_feature","sgf","CONCAT_WS(':', sgf.id_group, sgf.id_feature) name, sgf.id");
            //unset($this->feature['sgf']);
        }

    } // создаем связи групп модификаций и характеристик

    private function checkCreatMod($priceMods)
    {
        /** Проверяем наличие модификации по таблице <shop_modifications_feature>
         * 1 генерируем ключ, например: 47662:148,47663:151
         * 2 Сверяем ключ с БД
         */
        $this->debugging('funct', __FUNCTION__ . ' ' . __LINE__, __CLASS__, '[comment]');


        /** 1 генерируем ключ, например: 47662:148,47663:151 */

        foreach ($priceMods as $priceKey=>$priceUnit) {
            foreach ($priceUnit as $key=>$val) {
                $mod = $priceMods[$priceKey][$key];

                $keyHaving = '';
                $keyArray  = array();
                foreach ($mod['mod_param'] as $k => $i)
                    $keyArray[$i['shop_feature']] = $i['shop_feature'].':'.$i['shop_feature_value_list'];
                ksort($keyArray);
                foreach ($keyArray as $k => $i) $keyHaving = $keyHaving . $i . ',';
                $keyHaving = substr($keyHaving, 0, -1);

                $priceMods[$priceKey][$key]['keyHaving'] = $priceKey.'#'.$keyHaving;
            }
        }


        /** 2 Сверяем ключ с БД.  ответ БД через query # ответ query */

        $idsProducts = array_keys($priceMods);
        $idsProdStr = implode(",", $idsProducts);
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

    private function createModifications($priceMods)
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

                if ($mod['createMod']==true) {

                    $shopModifGroup = "";
                    foreach ($mod['mod_param'] as $k => $i) {
                        if (!empty($i['shop_modifications_group'])) {
                            $shopModifGroup = $i['shop_modifications_group'];
                            break;
                        }
                    }

                    /** заполняем, ставим заглушки в поля с null перед записью */
                    $shMods = array(
                        'id_mod_group'   => $shopModifGroup        ? $shopModifGroup        : null,
                        'id_price'       => $priceKey              ? $priceKey              : null,
                        'code'           => $mod['article']        ? $mod['article']        : "",
                        'value'          => $mod['price']          ? $mod['price']          : 0,
                        'value_opt'      => $mod['price_opt']      ? $mod['price_opt']      : 0,
                        'value_opt_corp' => $mod['price_opt_corp'] ? $mod['price_opt_corp'] : 0,
                        'value_purchase' => $mod['price_purchase'] ? $mod['price_purchase'] : 0,
                        'count'          => $mod['presence_count'] ? $mod['presence_count'] : -1,
                        'description'    => $mod['description']    ? $mod['description']    : "");


                    /** в массив */
                    if ($shMods['id_mod_group']!=null or $shMods['id_price']!=null)
                        array_push($newShopMod, $shMods);
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
        if (count($newShopMod)>0) {
            DB::query('SET foreign_key_checks = 0');
            DB::insertList('shop_modifications', $newShopMod, TRUE);
            DB::query('SET foreign_key_checks = 1');
        }


        /** 4 запрашиваем <idPrice>##<idModGroup> => <id> (с фильтрацией по идТовара) */

        $idsProducts = array_keys($priceMods);
        $idsProductsSrt = implode(",", $idsProducts);
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

                $keyParamGr = intval($priceKey).'##'.
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

    private function createModFeature($priceMods)
    {
        /** Записываем значения модификации  <shop_modifications_feature> */
        $this->debugging('funct', __FUNCTION__ . ' ' . __LINE__, __CLASS__, '[comment]');


        $newFeatures = array();
        foreach ($priceMods as $priceKey=>$priceUnit) {
            foreach ($priceUnit as $key=>$val) {
                $mod = $priceMods[$priceKey][$key];

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
            DB::query('SET foreign_key_checks = 0');
            DB::insertList('shop_modifications_feature', $newFeatures, TRUE);
            DB::query('SET foreign_key_checks = 1');
        }

    }  // Записываем значения модификации

    private function createModImg($priceMods)
    {
        /** Привязываем изображения к модификациям  <shop_modifications_img>
         * 1 получаем имяРисунка-ид (с фильтрацией по идТовара)
         * 2 заполняем массив на отправку в БД
         * 3 Чистим таблицу картинок модификаций перед записью
         * 4 записываем в <shop_modifications_img>
         */
        $this->debugging('funct', __FUNCTION__ . ' ' . __LINE__, __CLASS__, '[comment]');


        /** 1 получаем имяРисунка-ид (с фильтрацией по идТовара) */

        $idsProducts = array_keys($priceMods);
        $idsProductsSrt = implode(",", $idsProducts);
        $u = new DB("shop_img", "si");
        $u->select("si.id, si.picture, si.id_price");
        $u->where("si.id_price IN ($idsProductsSrt)");
        $l = $u->getList();
        $list = array();
        foreach ($l as $k => $i) {
            $key = $i['idPrice'].'##'.$i['picture'];
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
                        if ($mod['idModification'] and $list[$priceKey.'##'.$i]) {
                            array_push($newImgs, $shopModificationsImg);
                            $numSort = $numSort + 1;
                        }
                    }
                    array_push($idModifications, $mod['idModification']);
                }
            }
        }


        /** 3 Чистим таблицу картинок модификаций перед записью */

        if (count($idModifications)>0) {
            $idModificationsStr = implode(",", $idModifications);
            $u = new DB('shop_modifications_img', 'smi');
            $u->where('id_modification IN (?)', $idModificationsStr)->deletelist();
        }


        /** 4 записываем в <shop_modifications_img> */

        if (count($newImgs)>0) {
            DB::query('SET foreign_key_checks = 0');
            DB::insertList('shop_modifications_img', $newImgs, TRUE);
            DB::query('SET foreign_key_checks = 1');
        }

    } // Привязываем изображения к модификациям

    private function getRightData($item, $options, $key)
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
         * 5 обработчик значений/текста в Остатке
         * 6 Обрабатываем модификации (если есть)
         * 7 Cверяем наличие характеристик и значений
         * 8 устанновка значений по умолчанию при NULL (ЗАГЛУШКИ)
         * 9 получение списка изображений из ячеек Excel
         * 10 проверка корректности значений и запись в массив
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

        /** 1 создаем группы и связи с ними товаров */
        $id_gr = $this->CommunGroup($item, TRUE);

        /** 2 Добавляем меры (веса/объема) */
        $this->importData['measure'][] = array(
            'id_price' =>           $this->get('id', "/,(?!\s+)/ui", $item),
            "id_weight_view" =>     $this->getId('measures_weight', "/,(?!\s+)/ui", 'shop_measure_weight', 'name', $item)[0], /** НЕ ПРЕВОДИТЬ В int >> не отфильтровывается значеине при передаче */
            "id_weight_edit" =>     $this->getId('measures_weight', "/,(?!\s+)/ui", 'shop_measure_weight', 'name', $item)[1],
            "id_volume_view" =>     $this->getId('measures_volume', "/,(?!\s+)/ui", 'shop_measure_volume', 'name', $item)[0],
            "id_volume_edit" =>     $this->getId('measures_volume', "/,(?!\s+)/ui", 'shop_measure_volume', 'name', $item)[1]
        );

        /** 3 Добавляем сопутствующие товары */
        $accomp       = $this->get('id_acc', "/,(?!\s+)/ui", $item);
        $id_price_acc = $this->get('id', "/,(?!\s+)/ui", $item);
        /** если присутствуют сопутствующие - добавляем */
        if($accomp != NULL) {
            foreach ($accomp as $ac) {
                $this->importData['accomp'][] = array(
                    'id_price' => $id_price_acc,
                    'id_acc'   => $ac
                );
            }
        };

        // TODO  2 DB::query("SET foreign_key_checks = 0"); DB::insertList('shop_price_measure', $this->importData['measure'],TRUE); DB::query("SET foreign_key_checks = 1"); - пробовать удалить query
        // FIXME 3 тестировать на слияние файлов с конфликтами id
        // TODO  3 отвязывать id и переводить на code
        // TODO  2 updateListImport updateListImport попробовать отработанные ids товаров сохранять во временный файл (в конце цикла) и получать в начале цикла


        /** 4 ас.массив значений записи в БД */
        $Product = array(
            'id'             => $this->get('id', FALSE, $item),
            'id_group'       => $id_gr,
            'name'           => $this->get('name', FALSE, $item),
            'article'        => $this->get('article', FALSE, $item),
            'code'           => $this->get('code', FALSE, $item),
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

        /** 5 обработчик значений/текста в Остатке */
        if(!(int)$Product['presence_count'] and $Product['presence_count']!='0') {
            $Product['presence'] = $Product['presence_count'];
            $Product['presence_count'] = -1;
        }

        /** 6 Обрабатываем модификации (если есть) */
        $Product = $this->creationModificationsStart($Product, $item);

        /** 7 Cверяем наличие характеристик и значений */
        $Product = $this->creationFeature($Product, $item);

        /**
         * НЕ ЖЕЛАТЕЛЬНО ИСПОЛЬЗОВАНИЕ ФИЛЬТРАЦИИ ПУСТЫХ ПОЛЕЙ В $Product !
         * ПРИВОДИТ К "ЗАЛИПАНИЮ" ЗНАЧЕНИЙ В БАЗЕ ДАННЫХ
         */

        // // фильтрация пустых полей в продукте
        // $Product = array();
        // foreach ($Product0 as $ingredient=>$include) {
        //     if($include !== NULL) {$Product[$ingredient]= $include;};
        // };

        /** 8 устанновка значений по умолчанию при NULL (ЗАГЛУШКИ) */
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


        /** 9 получение списка изображений из ячеек Excel */
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
                            "id_price" => $this->get('id', "/,(?!\s+)/ui", $item),
                            "picture" => $result
                        );

                        /** главное изображение */
                        if (($result == $imgLL[0] and $imgKey == 'img_alt') or $imgKey == 'img') {
                            $newImg["default"] = 0; /** было 1, но тк могут быть модификации (и соответственно несколько первых изображений) */
                        } else $newImg["default"] = 0;
                        $this->importData['img'][] = $newImg;
                    }
                }
            }
        }

        /** 10 проверка корректности значений и запись в массив */
        $this->validationValues($Product);

        unset($item);
    } // ПОЛУЧИТЬ ПРАВИЛЬНЫЕ ДАННЫЕ

    private function validationValues($Product)
    {
        /** проверка корректности значений и запись в массив */
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');

        $line = $Product['lineNum']; /** номер строки (с учетом циклов) */
        $id = $Product['id'];

        $Product = $this->notText($Product, 'price', $line, "Цена пр.",'float', true);
        $Product = $this->notText($Product, 'price_opt', $line, "Цена опт.",'float', true);
        $Product = $this->notText($Product, 'price_opt_corp', $line, "Цена корп.",'float', true);
        $Product = $this->notText($Product, 'price_purchase', $line, "Цена закуп.",'float', true);
        $Product = $this->notText($Product, 'bonus', $line, "Цена бал.",'float', true);
        $Product = $this->notText($Product, 'is_market', $line, "Маркет",'int', true);
        $Product = $this->notText($Product, 'step_count', $line, 'Шаг количества','int', false);
        $Product = $this->notText($Product, 'weight', $line, 'Вес','float', true);
        $Product = $this->notText($Product, 'volume', $line, 'Объем','float', true);
        $Product = $this->notText($Product, 'min_count', $line, 'Мин.кол-во','int', true);

        // при пустом поле значение переменной $id==NULL
        if ((int)$id and !empty($Product['code']) and !empty($Product['name'])){
            $this->importData['products'][] = $Product;
        } else {
            if (!(int)$id               and !$_SESSION['errors']['id'])    $_SESSION['errors']['id'] = 'ОШИБКА[стр. '.$line.']: не корректное заполнение столбца "Ид."';
            if (empty($Product['code']) and !$_SESSION['errors']['code'])  $_SESSION['errors']['code'] = 'ОШИБКА[стр. '.$line.']: столбец "Код (URL)" не может быть пустым';
            if (empty($Product['name']) and !$_SESSION['errors']['name'])  $_SESSION['errors']['name'] = 'ОШИБКА[стр. '.$line.']: столбец "Наименование" не может быть пустым';

        }
    } // ПРОВЕРКА КОРРЕКТНОСТИ ЗНАЧЕНИЙ

    private function notText($data, $col, $line, $name, $iF, $zero)
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
            if (!$_SESSION['errors'][$col]) $_SESSION['errors'][$col]='ОШИБКА[стр. '.$line.']: столец "'.$name.'" не может содержать текст и '.$text.'. Произведена замена на '.$subs;
            $v = $subs;
        } elseif (empty($v)) $v = $subs;

        if ($iF=='int')       $data[$col] = (int)$v;
        elseif ($iF=='float') $data[$col] = round((float)$v, 2);

        return $data;
    } // проверка числового не отрицательного / большего, чем ноль

    private function addData()
    {
        /** Добавить данные
         *
         *
         *    @@    @@@@@@  @@@@@@     @@@@@@     @@    @@@@@@@@    @@
         *   @@@@   @@   @@ @@   @@    @@   @@   @@@@      @@      @@@@
         *  @@  @@  @@   @@ @@   @@    @@   @@  @@  @@     @@     @@  @@
         * @@@@@@@@ @@   @@ @@   @@    @@   @@ @@@@@@@@    @@    @@@@@@@@
         * @@    @@ @@@@@@  @@@@@@     @@@@@@  @@    @@    @@    @@    @@
         *
         *
         * 1 ставим ид в ключи массивов
         * 2 импорт товаров
         */
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');

        try{

            /** 1 ставим ид в ключи массивов */
            $this->childTablesWentTokeys();

            /** 2 импорт товаров */
            if (!empty($this->importData['products'])) {
                /** $this->mode == 'upd' отвечает за обновление (доавление к существующим, например, товарам) */
                if ($this->mode == 'upd')  $this->updateListImport();
                else                       $this->insertListImport();
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

    public function insertListImport()
    {
        /** ВСТАВКА: нет товара - добавляет, есть - пропускает
         *
         * 1: получаем id существующих товаров
         * 2: получаем главною группу для id_group
         * 3: сверяем id товара с shop_price,    если нет > отправляем к инсету на добавление
         *   если товар отсутствует - создаем
         *   иначе пропускаем
         * 3.1: начать транзакцию
         * 4: если id группы не получена (новая группа) - получаем
         * 5: получаем главною группу для shop_price id_group
         *   если имеем дело с новой группой и новым товаром
         *   ... или соответственно массивом
         * 6: для shop_price_group
         *   если значение одно - завернуть в массив для обработки
         *   если значения не соотнесены (отсутствовали данные по id) - совершить вторую попытку
         * 7: получаем элементы массива с определением главной группы
         *   если группа первая в списке - значит главная
         * 8: конец транзакции
         *
         * @param array $shopPriceData значения товара для shop_price
         * @param array $data_unit ответвление данныех продукта для обработки
         */
        $this->debugging('special', __FUNCTION__.' '.__LINE__, __CLASS__, 'передать импортируемые данные в БД таблица: "shop_price"');

        $shop_price = new DB('shop_price');                                           /** 1 получаем id существующих товаров */
        $shop_price->select('id');
        $id_list = $shop_price->getList();
        foreach($id_list as &$id_unit) $id_unit = $id_unit['id'];

        $data          = array();                                                     /** 2 получаем главною группу для id_group */
        $shopPriceData = array();
        $ids           = array();
        foreach ($this->importData['products'] as &$product_unit){
            $line=$product_unit['lineNum']; unset($product_unit['lineNum']); /** вынимаем номер строки из массива */

            if ($product_unit['id'] != $_SESSION['lastIdPrice']) {
                $data_unit = $product_unit;                                           /** 3 сверяем id товара с shop_price */

                foreach($id_list as $id_unit)
                    if($data_unit['id'] == $id_unit)  $availability = TRUE;
                if($availability == FALSE) {
                    DB::beginTransaction();                                           /** 3.1 начать транзакцию */

                    if (gettype($product_unit['id_group']) == 'array' and             /** 4 если id группы не получена (новая группа) - получаем */
                        gettype($product_unit['id_group'][0]) == 'array'
                    ) $id_group = $this->CommunGroup($product_unit['id_group'][0], FALSE);

                    if( gettype($product_unit['id_group']) == 'array' and             /** 5 получаем главною группу для shop_price id_group, если имеем дело с новой группой и новым товаром */
                        gettype($product_unit['id_group'][0]) == 'array'
                    )   $data_unit['id_group'] = $id_group[0];
                    elseif(gettype($product_unit['id_group']) == 'array')
                        $data_unit['id_group'] = $data_unit['id_group'][0];
                    array_push($shopPriceData, $data_unit);
                    $id = $data_unit['id'];

                    /** заполнение shop_price */
                    $this->insertListChildTablesBeforePrice($id, false);
                    $data_unit[0]        ? $data_unit = $data_unit : $data_unit = array($data_unit);
                    $this->mode != 'rld' ? $param = false          : $param = true;
                    DB::insertList('shop_price', $data_unit, $param);
                    $this->insertListChildTablesAfterPrice($id, false);
                    $this->checkCreateImg($product_unit['id'],$line);

                    $id_price = $data_unit[0]['id'];

                    /** удаление категории из shop_price_group */
                    if(gettype($product_unit['id_group']) == integer)             /** 6 для shop_price_group */
                        $product_unit['id_group'] = array($product_unit['id_group']);
                    elseif(gettype($product_unit['id_group']) == 'array' and gettype($product_unit['id_group'][0]) == 'array')
                        $product_unit['id_group'] = $id_group;
                    foreach ($product_unit['id_group'] as $i)
                        $this->deleteCategory($product_unit['id'], $i);

                    if(isset($product_unit['id'],$product_unit['id_group'][0])) {     /** 7 получаем элементы массива с определением главной группы */
                        foreach ($product_unit['id_group'] as $i) {
                            if(isset($product_unit['id'],$i)) {
                                $product_group_unit = array(
                                    'id_price' => (int) $product_unit['id'],
                                    'id_group' => (int) $i,
                                    'is_main'  => (bool) 0
                                );
                            };
                            if ($i == $product_unit['id_group'][0])
                                $product_group_unit['is_main'] = (bool) 1;
                            array_push($data,$product_group_unit);
                        };
                    };
                    $this->linkRecordShopPriceGroup($product_unit,$id_group,$id_price);

                    DB::commit();                                                     /** 8 конец транзакции */

                    $_SESSION['lastIdPrice'] = $id_price;
                    array_push($id_list, $id_price);
                };
            } else {
                /** ДЛЯ МОДИФИКАЦИЙ: даже если не пишем товар - записать картинки для привязки к модификациям */
                $this->checkCreateImg($product_unit['id'],$line);
            };
            $ids[ $product_unit['id'] ] = $this->thereModification[ $product_unit['code'] ];
        };
        $this->creationModificationsFinal($ids);
    } // вставка: нет товара-добавляет,  есть - пропускает

    public function updateListImport()
    {
        /** Лист продуктов на обновление (при отсутствии - добавление; при наличии - обновление (замена))
         * запись ПРОДУКТОВ и СВЯЗЕЙ группа-продукт
         *
         * 1: получаем id существующих товаров
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
         */
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');

        $product_list = $this->importData['products'];

        $shop_price = new DB('shop_price');                                        /** 1 */
        $shop_price->select('id');
        $id_list = $shop_price->getList();
        foreach($id_list as &$id_unit) $id_unit = $id_unit['id'];

        try {

            $ids = array();
            foreach ($product_list as &$product_unit){
                $line=$product_unit['lineNum']; unset($product_unit['lineNum']); /** вынимаем номер строки из массива */

                if ($product_unit['id'] != $_SESSION['lastIdPrice']) {
                    DB::beginTransaction();                                        /** 2 */

                    if (gettype($product_unit['id_group']) == 'array' and          /** 3 */
                        gettype($product_unit['id_group'][0]) == 'array'
                    ) $id_group = $this->CommunGroup($product_unit['id_group'][0], FALSE);

                    $data_unit = $product_unit;                                    /** 4 */
                    if (gettype($product_unit['id_group']) == 'array' and
                        gettype($product_unit['id_group'][0]) == 'array'
                    ) $data_unit['id_group'] = $id_group[0];
                    elseif(gettype($product_unit['id_group']) == 'array')
                        $data_unit['id_group'] = $data_unit['id_group'][0];
                    $pr_unit = new DB('shop_price');

                    foreach($id_list as $id_unit)                                  /** 5 */
                        if($data_unit['id'] == $id_unit)  $availability = TRUE;
                    if($availability == FALSE) {
                        $param = true;
                        if ($this->mode != 'rld') $param = false;
                        $id = $data_unit['id'];

                        $data_unit = array($data_unit);
                        $this->insertListChildTablesBeforePrice($id, false);
                        DB::insertList('shop_price', $data_unit, $param);
                        $id_price = $id;
                        $this->insertListChildTablesAfterPrice($id, false);
                        $this->checkCreateImg($product_unit['id'],$line);

                        $this->linkRecordShopPriceGroup($product_unit,$id_group,$id_price);

                        array_push($id_list,$id_price);

                    } else {
                        $this->insertListChildTablesBeforePrice($data_unit['id'], true);

                        $pr_unit->setValuesFields($data_unit);
                        $id_price = $pr_unit->save();

                        $this->insertListChildTablesAfterPrice($data_unit['id'], true);
                        $this->checkCreateImg($product_unit['id'],$line);
                    };

                    $_SESSION['lastIdPrice'] = $id_price;

                    DB::commit();                                                  /** 6 */
                } else {
                    /** ДЛЯ МОДИФИКАЦИЙ: даже если не пишем товар - записать картинки для привязки к модификациям */
                    $this->checkCreateImg($product_unit['id'],$line);
                };
                $ids[ $product_unit['id'] ] = $this->thereModification[ $product_unit['code'] ];
            };
            $this->creationModificationsFinal($ids);
        } catch (Exception $e) {
            DB::rollBack();                                                        /** 7 */
            return false;
        };
        return true;
    } // обновление: нет товара-добавление, есть - обновление (замена)

    private function childTablesWentTokeys()
    {
        /** id в ключи в массива <measure,features,prepare...>
         * @param array $keyTable      массив дочерних таблиц
         * @param array $newImportData новый сгруппированный по ид-товаров массив
         * @param array $i2            данные по одной записи ячейки
         * @param int   $id            ид товара
         */
        $keyTable = array_keys($this->importData);
        $newImportData = array();

        foreach ($keyTable as $k => $i) {
            if ($i != 'products') {
                foreach ($this->importData[$i] as $k2 => $i2) {
                    $id = $i2['id_price'];
                    if ($id) {
                        /** если у одного id несоклько значений - заворачиваем все в массив */
                        if ($newImportData[$i][$id]) array_push($newImportData[$i][$id], $i2);
                        else                                     $newImportData[$i][$id] = array($i2);
                    }
                    unset($this->importData[$i][$k2]);
                }
            } else {
                $newImportData[$i] = $this->importData[$i];
                unset($this->importData[$i]);
            }
        }
        $this->importData = $newImportData;
    } // id в ключи в массива

    private function checkCreateImg($id,$line)
    {
        /** Проверить есть ли изображения у товара - если нет, создать
         * @param array $this->importData данные из строки
         * @param num   $id               ид товара к которому привязаны записи таблиц
         * @param array $faileImgs        изображения товара из файла [0..]=>(id_price, picture, default)
         * @param bool  $isFile           true - файл корректен  false - не корректен
         * @param array $exten            возможные расширения файлов
         *
         * 1 получаем изображения товара из файла
         * 2 получаем изображения товара из БД
         * 3 отбираем отсутсвующие рисунки
         * 4 определяем главное изображение и возвращаем на запись
         */
        $this->debugging('funct', __FUNCTION__ . ' ' . __LINE__, __CLASS__, '[comment]');


        /** 1 получаем изображения товара из файла */
        $faileImgs = $this->importData['img'][$id];


        /** 2 получаем изображения товара из БД */
        $u = new DB("shop_img", "si");
        $u->select("si.id, si.picture");
        $u->where("si.id_price = '$id'");
        $l = $u->getList();
        $list = array();
        foreach ($l as $k => $i) {
            $list[$i['picture']] = $i['id'];
            unset($l[$k]);
        }
        unset($u,$l);


        /** 3 отбираем отсутсвующие рисунки*/
        if (!$faileImgs[0] and isset($faileImgs)) $faileImgs=array($faileImgs);
        $faileImgsTemp = array();
        foreach ($faileImgs as $k=>$i) {

            $exten=array('.jpg','.jpeg','.jpe','.png','.tif','.tiff','.gif','.bmp','.dib','.psd');
            $isFile=false;
            foreach ($exten as $kEX=>$iEX) {
                if (strpos(' '.$i['picture'],$iEX)) {
                    $isFile = true;
                    break;
                }
            }

            if ($isFile) {
                $list[$i['picture']] ?: array_push($faileImgsTemp, $i);
            } elseif (count($faileImgs)!=0) {
                if (!$_SESSION['errors']['img_alt']) $_SESSION['errors']['img_alt']='ОШИБКА[стр. '.$line.']: столец "Изображения" не корректное расширение файла';
            }

            unset($faileImgs[$k]);
        }
        $faileImgs = $faileImgsTemp; unset($faileImgsTemp);


        /** 4 определяем главное изображение и возвращаем на запись */
        if (count($list)==0 and $faileImgs[0]) $faileImgs[0]["default"]=1;
        $this->importData['img'][$id] = $faileImgs;


        if (count($faileImgs)>0)
            $this->insertListChildTables($id, array('img'=>'shop_img'), false); /** true=изменяемЗапись  false=создаем */
    } // Проверить есть ли изображения у товара - если нет, создать

    private function insertListChildTablesBeforePrice($id, $setValuesFields = false)
    {
        /** Заполнение дочерних таблиц До заполнения <shop_price>    1 импорт категорий    2 импорт мер (веса/объема)    3 импорт сопутствующих товаров */
        $tableNames = array('category'=>'shop_group', 'measure'=>'shop_price_measure', 'accomp'=>'shop_accomp');
        $this->insertListChildTables($id, $tableNames, $setValuesFields);
    } // Заполнение дочерних таблиц До заполнения <shop_price>

    private function insertListChildTablesAfterPrice($id, $setValuesFields = false)
    {
        /** Заполнение дочерних таблиц После заполнения <shop_price>    1 импорт характеристик    2 импорт изображений*/
        $tableNames = array('features'=>'shop_modifications_feature');
        $this->insertListChildTables($id, $tableNames, $setValuesFields);
    } // Заполнение дочерних таблиц После заполнения <shop_price>

    private function insertListChildTables($id, $tableNames, $setValuesFields=false)
    {
        /** Форма заполнения дочерних таблиц
         * @param array $this->importData    именной массив данных из строки (разбитые по таблицам ключТабл)
         * @param int   $id                  ид товара к которому привязаны записи таблиц
         * @param bool  $setValuesFields     true=изменяемЗапись  false=создаем
         * @param array $tableNames          ключТабл-имяТабл
         * @param array $tableData           массив данных для записи в БД
         * @param str   $tab                 имя таблицы
         */

        /** id в ключи в массива <measure,features,prepare...> */
        $keyTable = array_keys($this->importData);

        foreach ($keyTable as $k=>$i) {

            if ($tableNames[$i] and $this->importData[$i][$id]) {

                $tableData = $this->importData[$i][$id];
                $tableData[0] ?: $tableData=array($tableData);

                $shop_price = new DB($tableNames[$i]);
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
                        DB::query("SET foreign_key_checks = 0");
                        $tableData[0] ?: $tableData=array($tableData);
                        DB::insertList($tableNames[$i], $tableData,true);
                        DB::query("SET foreign_key_checks = 1");
                    } else {
                        /** удаляем старую запись*/
                        if ($id_list[$id]) {
                            DB::query("SET foreign_key_checks = 0");
                            $tab=$tableNames[$i];
                            $u = new DB($tab, $tab);
                            $u->where($tab.".id_price IN ($id)")->deleteList();
                            DB::query("SET foreign_key_checks = 1");
                        }
                        //$tableDB = new DB($tableNames[$i]);
                        //$tableDB->setValuesFields($tableData);
                        //$idDB = $tableDB->save();
                        DB::query("SET foreign_key_checks = 0");
                        $tableData[0] ? $tableData=$tableData : $tableData=array($tableData);
                        DB::insertList($tableNames[$i], $tableData,true);
                        DB::query("SET foreign_key_checks = 1");
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
