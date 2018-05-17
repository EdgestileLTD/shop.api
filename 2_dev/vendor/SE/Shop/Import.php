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


class Import extends Product
{

    private $data = array();
    private $cycleNum = 0;
    private $checkGroupIdName = FALSE; /** проверка наличия массива ид-имя группы в сессии */

    public $fieldsMap = array();

    /** поля в эксель */
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

        //writeLog($settings);
        //writeLog($_POST);
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
         */
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');


        /**
         * if     - превью      - чтение первых 100 строк
         * elseif - первый цикл - весь файл во временные
         */
        $this->cycleNum = $cycleNum;
        if ($prepare) {
            unset($_SESSION["cycleNum"]);
            unset($_SESSION["pages"]);
            $_SESSION["countPages"] = 0;
            unset($_SESSION["getId"]);
            $this->getDataFromFile($filename, $options, $prepare);
            $this->cycleNum = 0;
        } elseif ($this->cycleNum == 0) {
            $_SESSION["cycleNum"] = 0;
            unset($_SESSION["pages"]);
            $this->getDataFromFile($filename, $options, $prepare);
        }
        if ($prepare or $this->cycleNum != 0) {
            /** подгрузка кодов-ids категорий из БД */
            $this->getCodesIdsForSession();

            /** перебор записанных файлов */
            if ($prepare)       $this->data = $this->readTempFiles($this->cycleNum);
            else                $this->data = $this->readTempFiles($this->cycleNum-1);
            if (!$prepare and $this->cycleNum == 1)  {
                $this->prepareData($customEdition, $options); // заголовки
                $this->communications();                      // связи
                //writeLog($_SESSION["getId"]["code_group"]);
                //writeLog($_SESSION["getId"]["path_group"]);
            }
            elseif (!$prepare)  $this->fieldsMap = $_SESSION["fieldsMap"];
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
    } // Запуск импорта

    private function getCodesIdsForSession()
    {
        /**получить данные коды-ids групп в сессию (для генирации кодов новых групп - предотвращения совпадений)*/
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');

        $u = new DB("shop_group", "sg");
        $u->select("sg.id, sg.code_gr");
        $list = $u->getList();
        unset($u);

        foreach ($list as $k => $i) {
            $code_gr = $i['codeGr'];
            $_SESSION["getId"]['code_gr'][$code_gr] = $i['id'];
        }
    } // получить данные коды-ids групп

    private function addCategoryMain($code_group, $path_group)
    {
        /** Добавить Категорию / массив категорий
         * @param $code_group нумерованны массив или строка (автопреобразуется в массив)
         * @param $path_group нумерованны массив или строка (автопреобразуется в массив)
         */
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');

        // унификация (приводим к одному формату)
        if(gettype($code_group) == string)  $code_group = array($code_group);
        if(gettype($path_group) == string)  $path_group = array($path_group);
        // категории приходят массивом
        if(count($code_group) > 0 or count($path_group) > 0) {
            $list_group = max(count($code_group),count($path_group)) - 1;
            // перебираем пришедшие группы
            foreach (range(0, $list_group) as $unit_group) {
                if (!$this->check($code_group[$unit_group], 'code_group') or
                    !$this->check($path_group[$unit_group], 'path_group')
                ) {
                    // раскладываем путь, создаем группы по пути + код присваиваем ПОСЛЕДНЕЙ группе
                    $ways = (array)explode('/', $path_group[$unit_group]);
                    $countWays = count($ways) - 1;
                    $list_code = array();
                    $list_code[$countWays] = $code_group;
                    // проходим по пути проверяя наличие группы, при отсутствие - генерим, получаем id
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

        /** 1 определяем потребность в создании группы */
        $ar_pop_wa      = $ways[$number];
        $section_groups = array_slice($ways, 0, $number+1);
        // $section_groups - массив путь на кирилице strtolower(str_ireplace(" ", "-", rus2translit(implode("--", $section_groups))))
        // преобразование в "klassicheskie-kovry--kovry"
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
    } // Добавить категорию

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
         * @param $options
         * @param $prepare
         * @return bool
         * @throws \Exception
         */

        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');
        try{
            $temporaryFilePath = DOCUMENT_ROOT . "/files/tempfiles/";
            $file              = $temporaryFilePath.$filename;
            if(file_exists($file) and is_readable($file)){
                $extension = pathinfo($file, PATHINFO_EXTENSION);

                //  тест  1. xlsx33000редакт
                //  тест  2. csv33000редакт
                //  тест  3. xlsxMin
                //  тест  4. csvMin

                if ($prepare != TRUE)  $chunksize = 1000; /** объем временного файла */
                else                   $chunksize = 1000; /** объем врем файла для превью */

                if($extension == 'xlsx') {
                    $this->getNumberRowsFromFile($file, $extension, $prepare, $chunksize);

                    if (class_exists('PHPExcel_IOFactory', TRUE)) {
                        $startRow  = ($chunksize * $_SESSION["countPages"]);

                        PHPExcel_Autoloader::Load('PHPExcel_IOFactory');
                        $obj_reader = PHPExcel_IOFactory::createReader('Excel2007');
                        $obj_reader->setReadDataOnly(true);

                        $chunkSize   = 10000;
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
                    // если импортируем csv
                    $delimiter = $options['delimiter'];
                    // $encoding = $options['encoding'];

                    // автоопределитель разделителя
                    // (чувствителен к порядку знаков - по убывающей приоритетности)
                    if($delimiter == 'auto') {
                        $delimiters_first_line = array('\t' => 0,
                                                       ';'  => 0,
                                                       ':'  => 0);
                        $delimiters_second_line = $delimiters_first_line;
                        $delimiters_final       = array();

                        // читаем первые 2 строки для обработки
                        $handle      = fopen($file, 'r');
                        $first_line  = fgets($handle);
                        $second_line = fgets($handle);
                        fclose($handle);

                        // производим подсчет знаков из $delimiters_first/second_line в обеих строках
                        foreach ($delimiters_first_line as $delimiter => &$count)
                            $count = count(str_getcsv($first_line, $delimiter, $options['limiterField']));
                        foreach ($delimiters_second_line as $delimiter => &$count)
                            $count = count(str_getcsv($second_line, $delimiter, $options['limiterField']));
                        $delimiter = array_search(max($delimiters_first_line), $delimiters_first_line);

                        // сопоставляем колво знаков - совпадает, в $delimiters_final
                        foreach($delimiters_first_line as $key => $value) {
                            if($delimiters_first_line[$key] == $delimiters_second_line[$key])
                                $delimiters_final[$key] = $value;
                        };

                        // получаем максимальное совпадение из $delimiters_final - переназначаем разделитель с ";"
                        if(count($delimiters_final) > 1) {
                            $delimiters_final2 = array_keys($delimiters_final, max($delimiters_final));
                            foreach($delimiters_final2 as $value) $delimiter = $value;
                        } else
                            foreach($delimiters_final as $key => $value) $delimiter = $key;
                    };

                    // формируем массив
                    $row = 0;
                    $cycleNum = 0;
                    if (($handle = fopen($file, "r")) !== FALSE) {
                        /**
                         * @var $handle                          Корректный файловый указатель на файл
                         * @var int $length                      макс длина строки
                         * @var string $delimiter                разделитель ячеек
                         * @var string $options['limiterField']  разделитель вложенного текста
                         * @var array $line                      масив ячеек строчки
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
                                // если не превью
                                $this->writTempFiles($this->data, $cycleNum);
                                $cycleNum += 1;
                                $row = 0;
                                $this->data = array();
                            } else $row++;
                        }
                        if ($row > 0) {
                            $this->writTempFiles($this->data, $cycleNum); // запись остатка
                            $cycleNum += 1;
                        }
                        fclose($handle);
                        $_SESSION["countPages"] = 1; // заглушка для прогресс бара
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

    private function prepareData($userData, $options)
    {
        /** привязываем Заголовки к Номерам Столбцов
         *
         * 1 если приходит пользовательская редакция - подставляем. Иначе берем стандартно
         *
         * Готовим данные
         * @param $userData
         * @param $options
         */

        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');

        /** 1 */
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

        foreach ($this->fields as $name => $rus)
            foreach ($array as $key => $field)
                if ($field == $rus)
                    $this->fieldsMap[$name] = $key;
        $_SESSION["fieldsMap"] = $this->fieldsMap;
    } // привязываем Заголовки к Номерам Столбцов

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
                    if ($this->rowCheck($item) == TRUE)  $this->getRightData($item, $options);
                    $this->data[$key] = null;
                }

            }
        }
        /** 4 если заголовок отсутствует - ставим заглушку */
        if(empty($this->importData['prepare'][0])) {
            $count = count($this->importData['prepare'][1]);
            $this->importData['prepare'][0] = array_fill( 0, $count , null);
        }
    } // Данные итератора

    // TODO Тестить работу связок из сесии
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
            //writeLog(iconv('utf-8', 'windows-1251', $item[$this->fieldsMap[$key]]));

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
    } // получение ид группы

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
        if(gettype($item[$this->fieldsMap[$key]]) == string)
            if ($delimiter !== FALSE) $fieldsM = preg_split($delimiter, $item[$this->fieldsMap[$key]]);
        else
            $fieldsM = array((string) $item[$this->fieldsMap[$key]]);
        /** 2 если осталась строкой - приводим к общему формату */
        if(gettype($fieldsM) == string or gettype($fieldsM) == integer) $fieldsM = array($fieldsM);


        $itemFinish = array();
        foreach ($fieldsM as $fM) {
            if($fM == '') $fM = NULL;
            //if (isset($fM) and !empty($fM))
            array_push($itemFinish, $fM); /** добавить элемент в масив */
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
    } // Создание Групп и Связей с ними товаров

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

    private function getRightData($item, $options)
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
         * 6 устанновка значений по умолчанию при NULL (ЗАГЛУШКИ)
         * 7 получение списка изображений из ячеек Excel
         *
         * @param $item данные по КАЖДОМУ товару
         */
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');

        // // Добавляем категории
        // $this->addCategory(
        //     $this->get('id_group', "/,(?!\s+)/ui", $item),
        //     $this->get('code_group', "/,(?!\s+)/ui", $item),
        //     $this->get('path_group', "/,(?!\s+)/ui", $item)
        // );

        //writeLog($options['delimiter']);

        /** 1 создаем группы и связи с ними товаров */
        $id_gr = $this->CommunGroup($item, TRUE);

        /** 2 Добавляем меры (веса/объема) */
        $this->importData['measure'][] = array(
            'id_price' =>           $this->get('id', "/,(?!\s+)/ui", $item),
            "id_weight_view" =>     $this->getId('measures_weight', "/,(?!\s+)/ui", 'shop_measure_weight', 'name', $item)[0], // НЕ ПРЕВОДИТЬ В int >> не отфильтровывается значеине при передаче
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

        /** 4 ас.массив значений записи в БД */
        $Product = array(
            'id' =>                 $this->get('id', FALSE, $item),
            'id_group' =>           $id_gr,
            'name' =>               $this->get('name', FALSE, $item),
            'article' =>            $this->get('article', FALSE, $item),
            'code' =>               $this->get('code', FALSE, $item),
            'price' =>              (int) $this->get('price', FALSE, $item),
            'price_opt' =>          (int) $this->get('price_opt', FALSE, $item),
            'price_opt_corp' =>     (int) $this->get('price_opt_corp', FALSE, $item),
            'price_purchase' =>     (int) $this->get('price_purchase', FALSE, $item),
            'bonus' =>     			(int) $this->get('bonus', FALSE, $item),
            'presence_count' =>     $this->get('presence_count', FALSE, $item),
            'presence' =>           NULL, // если Остаток текстовый - поле заполняется ниже
            'step_count' =>         $this->get('step_count', FALSE, $item),

            'weight' =>             $this->get('weight', FALSE, $item),
            'volume' =>             $this->get('volume', FALSE, $item),

            'measure' =>            $this->get('measure', "/,(?!\s+)/ui", $item),
            'note' =>               $this->get('note', FALSE, $item),
            'text' =>               $this->get('text', FALSE, $item),
            'curr' =>               $this->get('curr', FALSE, $item),

            'enabled' =>            $this->get('enabled', FALSE, $item),//
            'flag_new' =>           $this->get('flag_new', FALSE, $item),
            'flag_hit' =>           $this->get('flag_hit', FALSE, $item),
            'is_market' =>          (int) $this->get('is_market', FALSE, $item),

            "img_alt" =>            $this->get('img_alt', "/,(?!\s+)/ui", $item),
            'min_count' =>          (int) $this->get('min_count', FALSE, $item),
            'id_brand' =>           $this->getId('id_brand', FALSE, 'shop_brand', 'name', $item),

            'title' =>              $this->get('title', FALSE, $item),
            'keywords' =>           $this->get('keywords', FALSE, $item),
            'description' =>        $this->get('description', FALSE, $item)
            /** смотреть в БД */

        );

        /** 5 обработчик значений/текста в Остатке */
        if((int)$Product['presence_count'] == 0) {
            $Product['presence'] = $Product['presence_count'];
            $Product['presence_count'] = -1;
        }

        /**
         * НЕ ЖЕЛАТЕЛЬНО ИСПОЛЬЗОВАНИЕ ФИЛЬТРАЦИИ ПУСТЫХ ПОЛЕЙ В $Product !
         * ПРИВОДИТ К "ЗАЛИПАНИЮ" ЗНАЧЕНИЙ В БАЗЕ ДАННЫХ
         */

        // // фильтрация пустых полей в продукте
        // $Product = array();
        // foreach ($Product0 as $ingredient=>$include) {
        //     if($include !== NULL) {$Product[$ingredient]= $include;};
        // };

        /** 6 устанновка значений по умолчанию при NULL (ЗАГЛУШКИ) */
        $substitution = array(
            'curr'     => 'RUB',
            'enabled'  => 'Y',
            'flag_new' => 'N',
            'flag_hit' => 'N'
        );
        // сверяем значения ячеек продукта со списком замены
        foreach ($Product as $ingredient => &$include)
            foreach ($substitution as $ing=>$inc)
                if($ingredient == $ing and $include == NULL)
                    $Product[$ingredient] = $inc;


        /** 7 получение списка изображений из ячеек Excel */
        $imgList = array('img_alt','img', 'img_2', 'img_3', 'img_4', 'img_5', 'img_6', 'img_7', 'img_8', 'img_9', 'img_10');

        foreach ($imgList as $imgKey){
            if($imgLL = $this->get($imgKey, "/,(?!\s+)/ui", $item)){

                // разложение на элементы
                //$imgLL = iconv('utf-8', 'windows-1251', $imgLL);
                //$imgLL = explode("/,(?!\s+)/ui", $imgLL);

                /** преобразование информации по изображения под таблицу shop_img */
                foreach ($imgLL as $result) {
                    $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');

                    if ($result != '') {

                        $newImg = array(
                            "id_price" => $this->get('id', "/,(?!\s+)/ui", $item),
                            "picture" => $result
                        );

                        /** главное изображение */
                        if (($result == $imgLL[0] and $imgKey == 'img_alt') or $imgKey == 'img') {
                            $newImg["default"] = 1;
                        } else $newImg["default"] = 0;
                        $this->importData['img'][] = $newImg; // возможно это нужно вынести за скобки
                    }
                }
            }
        }
        $this->importData['products'][] = $Product;
        unset($item);
    } // Получить правильные данные

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
         * 1 удалить все строки в таблицах БД...
         * 2 импорт категорий
         * 3 импорт мер (веса/объема)
         * 4 импорт сопутствующих товаров
         * 5 импорт товаров
         * 6 импорт изображений
         */
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');

        try{
            //writeLog($this->mode,'MODE');
            $param = true;
            /** 1 удалить все строки в таблицах БД... */
            if ($this->mode == 'rld' and $_SESSION["cycleNum"] == 1) {
                DB::query("SET foreign_key_checks = 0");
                DB::query("TRUNCATE TABLE shop_group");
                DB::query("TRUNCATE TABLE shop_price");
                DB::query("TRUNCATE TABLE shop_price_group");
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
            } elseif ($_SESSION["cycleNum"] == 1)  $param = false;

            /** 2 импорт категорий */
            if (!empty($this->importData['category'])){
                DB::query("SET foreign_key_checks = 0");
                DB::insertList('shop_group', $this->importData['category'],TRUE);
                DB::query("SET foreign_key_checks = 1");
            }

            /** 3 импорт мер (веса/объема) */
            if (!empty($this->importData['measure'])){
                DB::query("SET foreign_key_checks = 0");
                DB::insertList('shop_price_measure', $this->importData['measure'],TRUE);
                DB::query("SET foreign_key_checks = 1");
            }

            /** 4 импорт сопутствующих товаров */
            if (!empty($this->importData['accomp'])){
                DB::query("SET foreign_key_checks = 0");
                DB::insertList('shop_accomp', $this->importData['accomp'],TRUE);
                DB::query("SET foreign_key_checks = 1");
            }

            /** 5 импорт товаров */
            if (!empty($this->importData['products'])) {
                /** $this->mode == 'upd' отвечает за обновление (доавление к существующим, например, товарам) */
                if ($this->mode == 'upd')  $this->updateListImport();
                else                       $this->insertListImport();
                DB::query("SET foreign_key_checks = 1");
            }

            /** 6 импорт изображений */
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
    } // Добавить данные

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
         */
        $this->debugging('special', __FUNCTION__.' '.__LINE__, __CLASS__, 'передать импортируемые данные в БД таблица: "shop_price"');

        //writeLog($this->importData['products']); // прослушивание передачи продукта

        $shop_price = new DB('shop_price');                                           /** 1 */
        $shop_price->select('id');
        $id_list = $shop_price->getList();
        foreach($id_list as &$id_unit) $id_unit = $id_unit['id'];

        $data = array();                                                              /** 2 */
        $id_group_list = array();
        foreach ($this->importData['products'] as &$product_unit){

            $data_unit = $product_unit;                                               /** 3 */
            foreach($id_list as $id_unit)
                if($data_unit['id'] == $id_unit)  $availability = TRUE;
            if($availability == FALSE) {
                DB::beginTransaction();                                               /** 3.1 */

                if (gettype($product_unit['id_group']) == 'array' and                 /** 4 */
                    gettype($product_unit['id_group'][0]) == 'array'
                ) $id_group = $this->CommunGroup($product_unit['id_group'][0], FALSE);

                if( gettype($product_unit['id_group']) == 'array' and                 /** 5 */
                    gettype($product_unit['id_group'][0]) == 'array'
                )   $data_unit['id_group'] = $id_group[0];
                elseif(gettype($product_unit['id_group']) == 'array')
                    $data_unit['id_group'] = $data_unit['id_group'][0];
                array_push($id_group_list, $data_unit);


                if(gettype($product_unit['id_group']) == integer)                     /** 6 */
                    $product_unit['id_group'] = array($product_unit['id_group']);
                elseif(gettype($product_unit['id_group']) == 'array' and gettype($product_unit['id_group'][0]) == 'array')
                    $product_unit['id_group'] = $id_group;
                foreach ($product_unit['id_group'] as $i)
                    $this->deleteCategory($product_unit['id'], $i);

                if(isset($product_unit['id'],$product_unit['id_group'][0])) {         /** 7 */
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
                $this->linkRecordShopPriceGroup($product_unit,$id_group,$data_unit[0]['id']);
                DB::commit();                                                         /** 8 */
            };
        };
        DB::insertList('shop_price', $id_group_list,$param);
        DB::query("SET foreign_key_checks = 0");
        DB::insertList('shop_price_group', $data);
    } // ВСТАВКА: нет товара - добавляет, есть - пропускает

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

        $shop_price = new DB('shop_price');                                    /** 1 */
        $shop_price->select('id');
        $id_list = $shop_price->getList();
        foreach($id_list as &$id_unit) $id_unit = $id_unit['id'];

        try {

            foreach ($product_list as &$product_unit){
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
                    $data_unit = array($data_unit);
                    DB::insertList('shop_price', $data_unit,$param);
                    $id_price = $data_unit[0]['id'];
                }else {
                    $pr_unit->setValuesFields($data_unit);
                    $id_price = $pr_unit->save();
                };


                $this->linkRecordShopPriceGroup($product_unit,$id_group,$id_price);
                DB::commit();                                                  /** 6 */
            };
        } catch (Exception $e) {
            DB::rollBack();                                                    /** 7 */
            return false;
        };
        return true;
    } // Лист продуктов на обновление

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
            //writeLog('$delete_id_list // на удаление');writeLog($delete_id_list);

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
                        //writeLog('$category_unit // добавление');writeLog($category_unit);
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
        //writeLog($data);
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
