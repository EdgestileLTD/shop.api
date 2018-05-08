<?php

namespace SE\Shop;

require_once $_SERVER['DOCUMENT_ROOT'] . '/api/lib/Spout/Autoloader/autoload.php';

use SE\Shop\Product;
use SE\DB;

use Box\Spout\Reader\ReaderFactory;
use Box\Spout\Writer\WriterFactory;
use Box\Spout\Common\Type;


class ProductExport extends Product
{

    public function previewExport($temporaryFilePath)
    {
        /**
         *  @@@@@@ @@@@@@ @@@@@@ @@    @@    @@@@@@ @@  @@ @@@@@@  | превью экспорта
         *  @@  @@ @@  @@ @@     @@    @@    @@      @@@@  @@  @@  |
         *  @@@@@@ @@@@@@ @@@@@@  @@  @@     @@@@@@   @@   @@@@@@  |
         *  @@     @@ @@  @@       @@@@      @@      @@@@  @@      |
         *  @@     @@  @@ @@@@@@    @@       @@@@@@ @@  @@ @@      |
         */

        $this->debugging('funct', __FUNCTION__ . ' ' . __LINE__, __CLASS__, '[comment]');
        $this->rmdir_recursive($temporaryFilePath);  // очистка директории с временными файлами

        $headerCSV = array();
        foreach ($this->rusCols as $k => $v)
            array_push($headerCSV, $v);

        /** прикрепляем столбцы модификаций */
        $modsCols = $this->modsCols();
        foreach ($modsCols as $v) {
            array_push($headerCSV, $v['name']);
        }

        /**
         * ПОЛУЧЕНИЕ СВЯЗИ МОДИФИКАЦИЯ-ПАРАМЕТР  для чекбокс листа
         *
         * Схема:
         * shop_modifications_group  <  shop_modifications  <    shop_modifications_feature    >  shop_feature
         * name                  id  <  id_mod_group    id  <  id_modification     id_feature  >  id      name
         *
         * не доделана : нужно проверять работоспособность запроса
         */

        // $u = new DB('shop_modifications_feature', 'smf');
        // $u->select('smg.name modification, sf.name feature');
        // $u->leftJoin('shop_modifications sm', 'sm.id = smf.id_modification');
        // $u->leftJoin('shop_modifications_group smg', 'smg.id = sm.id_mod_group');
        // $u->leftJoin('shop_feature sf', 'sf.id = smf.id_feature');
        // $u->where('smg.name != ""');
        // $u->orderBy('smg.name, sf.name');
        // $u->groupBy('smg.name, sf.name');
        // $result = $u->getList();
        // unset($u);

        return $headerCSV;
    } // превью экспорта

    public function mainExport($input, $fileName, $filePath, $oldFilePath, $temporaryFilePath)
    {

        /**
         *  @@     @@    @@    @@@@@@ @@    @@    @@@@@@ @@  @@ @@@@@@ @@@@@@ @@@@@@ @@@@@@@@  | Экспорт
         *  @@@   @@@   @@@@     @@   @@@   @@    @@      @@@@  @@  @@ @@  @@ @@  @@    @@     |
         *  @@ @@@ @@  @@  @@    @@   @@@@@ @@    @@@@@@   @@   @@@@@@ @@  @@ @@@@@@    @@     |
         *  @@  @  @@ @@@@@@@@   @@   @@  @@@@    @@      @@@@  @@     @@  @@ @@ @@     @@     |
         *  @@     @@ @@    @@ @@@@@@ @@   @@@    @@@@@@ @@  @@ @@     @@@@@@ @@  @@    @@     |
         *
         * @param $writer;   // данные по временным файлам
         * @param $line;     // номер линии
         * @param $column;   // колонки
         * @param $limit;    // макс выдачи в одном запросе к БД
         * @param $offset;   // начальный номер выдачи
         * @param $cycleNum; // номер нынешней страницы
         * @return $pages;   // кол-во страниц
         */

        $this->debugging('funct', __FUNCTION__ . ' ' . __LINE__, __CLASS__, '[comment]');

        // объявление параметров для экспорта
        $cycleNum                = $input['cycleNum'];
        $limit                   = 1000;
        $offset                  = $cycleNum * $limit;
        $line                    = 1;
        $formData                = $input['columns'];
        $goodsIndex              = [];
        $column                  = array();
        $headerCSV               = array();
        $writer                  = array();
        $this->temporaryFilePath = $temporaryFilePath;

        /**
         * запрос на получение __листа_товаров__
         * Пакетный запрос к БД с перезагрузкой соединения к БД
         * сбор данных о $limit товарах за проход __цикла__
         */
        list($mainRequest, $pages) = $this->shopPrice($limit, $offset);
        $goodsL  = $mainRequest->getList($limit, $offset);  // получение лимитированного списка товаров


        if (!empty($goodsL)) {
            $this->exportCycle(
                $writer, $line, $goodsL, $goodsIndex,
                $filePath, $formData, $cycleNum, $column, $pages, $fileName
            );  // Запись из БД в файл
        }

        if($cycleNum == $pages-1)
            $this->assembly($pages, $filePath);

        return $pages;   // возврат в Ajax колво страниц в формируемом файле
    } // Экспорт

    public function exportCycle( $writer, $line, $goodsL, $goodsIndex,
                                 $filePath, $formData, $cycleNum, $column, $pages )
    {

        /**
         *  @@@@@@ @@  @@ @@@@@@ @@     @@@@@@  | цикл экспорта
         *  @@     @@  @@ @@     @@     @@      |
         *  @@      @@@@  @@     @@     @@@@@@  |
         *  @@       @@   @@     @@     @@      |
         *  @@@@@@   @@   @@@@@@ @@@@@@ @@@@@@  |
         */

        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');

        // фильтрация значений
        $goodsLFilter = [];
        foreach ($goodsL as $i) {
            if ($i[stepCount] == 1) {
                $i[stepCount] = '';
            }
            array_push($goodsLFilter, $i);
        }
        $goodsL = $goodsLFilter;

        $this->debugging('special', __FUNCTION__ . ' ' . __LINE__, __CLASS__, 'экспортируемые данные');

        $modsCols      = $this->modsCols();      // особенности
        $groups        = $this->groups();        // группы товаров

        // TODO если удастся снизить время старта цикла - можно уменьшить кол-во товаров, записываемых во временный файл
        // TODO нужен ли вкл/выкл экспорта модификаций?

        /** получаем ids товаров для запроса модификаций */
        $idsProducts = array();
        foreach ($goodsL as $l)
            array_push($idsProducts, $l['id']);

        $modifications = $this->modifications($idsProducts); // модификации товаров

        $excludingKeys = array("idGroup", "presence", "idModification");
        $rusCols       = $this->rusCols;
        if ($cycleNum == 0)
            $header    = array_keys($goodsL[0]);
        $headerCSV     = [];

        $tempGoodsL = array();

        foreach ($goodsL as $good) {
            if (CORE_VERSION != "5.2") {
                $good["category"] = parent::getGroup53($groups, $good["idGroup"]);
            } else {
                $good["category"] = parent::getGroup($groups, $good["idGroup"]);
            }
            array_push($tempGoodsL, $good);
        }
        $goodsL = $tempGoodsL; $tempGoodsL = array();

        foreach ($goodsL as $item) {
            foreach ($modsCols as $col) {
                $item[$col['name']] = null;
            }
            $goodsIndex[$item["id"]] = &$item;
            array_push($tempGoodsL, $item);
        }
        $goodsL = $tempGoodsL; unset($tempGoodsL);

        $goodsL = $this->mergerWithMoffification($goodsL, $modifications); // сливаем списки товаров и их модификаций

        if ($cycleNum == 0) {
            foreach ($header as $col)
                if (!in_array($col, $excludingKeys)) {
                    $col         = iconv('utf-8', 'utf-8', $rusCols[$col] ? $rusCols[$col] : $col); // CP1251
                    $headerCSV[] = $col;
                }

            /** прикрепляем столбцы модификаций */
            foreach ($modsCols as $v) {
                array_push($headerCSV, $v['name']);
            }
        }

        /**
         * ФОРМИРОВАНИЕ ФАЙЛА
         *
         * определяем колво заголовков и генерируем список столбцов по длине
         *
         * замена значений на пользовательские
         * размета по столбцам (координаты)
         * записываем заголовки
         * вывод товаров без модификаций
         * вывод товаров с модификациями
         * записываем в файл
         */

        $last_column               = count($headerCSV);
        $column_number             = 0;
        $i                         = 0;
        $header                    = null;
        $lastId                    = null;
        $goodsItem                 = [];

        list($goodsL, $goodsIndex) = $this->customValues($formData, $headerCSV, $numColumn, $goodsL, $goodsIndex);
        if ($cycleNum == 0)
            $column                = $this->columnLayout($column_number, $column, $last_column);
        if ($cycleNum == 0)
            list($writer, $line)   = $this->recordHeaders($writer, $headerCSV, $line);
        list($writer, $line)       = $this->recorRow($writer, $goodsL, $excludingKeys, $column, $line);
        $this->writTempFiles($writer, $cycleNum);
    } // цикл / завершение экспорта

    private function shopPrice($limit, $offset)
    {
        /**
         *  @@@@@@@@ @@@@@@ @@@@@     @@    @@@@@@ @@     @ | ПОЛУЧЕНИЕ
         *     @@    @@  @@ @@  @@   @@@@   @@  @@ @@     @ | листа товаров
         *     @@    @@  @@ @@@@@   @@  @@  @@@@@@ @@@@@@ @ |
         *     @@    @@  @@ @@  @@ @@@@@@@@ @@     @@  @@ @ |
         *     @@    @@@@@@ @@@@@  @@    @@ @@     @@@@@@ @ |
         */


        $this->debugging('funct', __FUNCTION__ . ' ' . __LINE__, __CLASS__, '[comment]');
        // получаем данные из БД
        $u = new DB('shop_price', 'sp');
        $u->reConnection();  // перезагрузка запроса
        $u->select('COUNT(*) `count`');
        $result = $u->getList();
        $count = $result[0]["count"];
        $pages = ceil($count / $limit);

        // подключение к shop_price
        $u = new DB('shop_price', 'sp');

        // НАЧАЛО ЗАПРОСА
        $select = '
                sp.id id,
                NULL category';

        if (CORE_VERSION != "5.2") {
            // получение дополнительных категорий
            $select .= ',

                    GROUP_CONCAT(DISTINCT
                        spg.id_group
                        ORDER BY spg.is_main DESC
                        SEPARATOR ","
                    ) AS shop_id_group,

                    GROUP_CONCAT(DISTINCT
                        spg.id_group
                        ORDER BY spg.is_main DESC
                        SEPARATOR ","
                    ) AS idGroup,

                    GROUP_CONCAT(DISTINCT
                        sg.code_gr
                        ORDER BY spg.is_main DESC
                        SEPARATOR ","
                    ) AS code_group

                ';
        } else {
            $select .= ',
                    sp.id_group shop_id_group,
                    sp.id_group IdGroup,
                    sg.code_gr code_group
                ';
        }
        $select .= ',

                sp.code code, sp.article article,
                sp.name name, sp.price price,sp.price_purchase price_purchase, sp.price_opt price_opt, sp.price_opt_corp price_opt_corp, sp.bonus bonus,
                sb.name name_brand, sp.curr codeCurrency, sp.measure measurement,
                sp.presence_count count, sp.step_count step_count,
                sp.presence presence, sp.flag_new, sp.flag_hit, sp.enabled, sp.is_market,
                sp.weight weight, sp.volume volume,

                CONCAT(
                    IFNULL(smw1.name, \'\'),\',\',
                    IFNULL(smw2.name, \'\')
                ) measuresWeight,

                CONCAT(
                    IFNULL(smv1.name, \'\'),\',\',
                    IFNULL(smv2.name, \'\')
                ) measuresVolume,

                sp.min_count,

                GROUP_CONCAT(DISTINCT
                    si.picture
                    SEPARATOR \',\'
                ) AS images,

                GROUP_CONCAT(DISTINCT
                    sa.id_acc
                    SEPARATOR \',\'
                ) AS idAcc,

                sp.title metaHeader, sp.keywords metaKeywords, sp.description metaDescription,
                sp.note description, sp.text fullDescription, sm.id idModification,

                (
                    SELECT GROUP_CONCAT(
                        CONCAT_WS(\'#\', sf.name,
                            IF(
                                smf.id_value IS NOT NULL, sfvl.value, CONCAT(
                                    IFNULL(smf.value_number, \'\'),
                                    IFNULL(smf.value_bool, \'\'),
                                    IFNULL(smf.value_string, \'\')
                                )
                            )
                        ) SEPARATOR \';\'
                    ) features

                    FROM shop_modifications_feature smf
                    INNER JOIN shop_feature sf ON smf.id_feature = sf.id AND smf.id_modification IS NULL
                    LEFT JOIN shop_feature_value_list sfvl ON smf.id_value = sfvl.id
                    WHERE smf.id_price = sp.id
                    GROUP BY smf.id_price
                ) features
            ';

        /** все запршиваемые поля должны использоваться в импорте или удалятся при обработке,
         *  иначе идет сдвиг столбцов и модификации не отображаются
         */

        if (CORE_VERSION != "5.2") {
            $u->select($select);
            $u->leftJoin("shop_price_group spg", "spg.id_price = sp.id");
            $u->leftJoin('shop_group sg', 'sg.id = spg.id_group');
        } else {
            $u->select($select);
            $u->leftJoin('shop_group sg', 'sg.id = sp.id_group');
        };


        $u->leftJoin('shop_modifications sm', 'sm.id_price = sp.id');
        $u->leftJoin('shop_img si', 'si.id_price = sp.id');
        $u->leftJoin('shop_brand sb', 'sb.id = sp.id_brand');

        $u->leftJoin('shop_price_measure spm', 'spm.id_price = sp.id');
        $u->leftJoin('shop_measure_weight smw1', 'smw1.id = spm.id_weight_view');
        $u->leftJoin('shop_measure_weight smw2', 'smw2.id = spm.id_weight_edit');
        $u->leftJoin('shop_measure_volume smv1', 'smv1.id = spm.id_volume_view');
        $u->leftJoin('shop_measure_volume smv2', 'smv2.id = spm.id_volume_edit');
        $u->leftJoin('shop_accomp sa', 'sa.id_price = sp.id');

        $u->orderBy('sp.id');
        $u->groupBy('sp.id');

        return [$u, $pages];
    } // получение листа товаров

    private function modsCols()
    {
        /**
         *  @@@@@@@@ @@@@@@ @@@@@     @@    @@@@@@ @@     @     @     | ПОЛУЧЕНИЕ
         *     @@    @@  @@ @@  @@   @@@@   @@  @@ @@     @     @     | особенностей товаров
         *     @@    @@  @@ @@@@@   @@  @@  @@@@@@ @@@@@@ @  @@@@@@@  | групп        товаров
         *     @@    @@  @@ @@  @@ @@@@@@@@ @@     @@  @@ @     @     | модификаций  товаров
         *     @@    @@@@@@ @@@@@  @@    @@ @@     @@@@@@ @     @     |
         */

//        $u = new DB('shop_modifications', 'sm');
//        $u->select('GROUP_CONCAT(DISTINCT smg.name, "#", sf.name SEPARATOR "##") AS `name`');
//        $u->innerJoin('shop_modifications_group smg',   'smg.id = sm.id_mod_group');
//        $u->innerJoin('shop_modifications_feature smf', 'sm.id = smf.id_modification');
//        $u->innerJoin('shop_feature sf',                'sf.id = smf.id_feature');
//        $tempModHeader = $u->getList();
//        $tempModHeader = explode("##", $tempModHeader[0]['name']);



        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, 'экспортируемые данные');
        $u = new DB('shop_feature', 'sf');
        $u->reConnection();  // перезагрузка запроса
        $u->select('sf.id Id, CONCAT_WS(\'#\', smg.name, sf.name) name');
        $u->innerJoin('shop_group_feature sgf', 'sgf.id_feature = sf.id');
        $u->innerJoin('shop_modifications_group smg', 'smg.id = sgf.id_group');
        $u->groupBy('sgf.id');
        $u->orderBy('smg.name');
        $modsCols = $u->getList();
        unset($u);
        return $modsCols;
    } // <особенности, группы, модификации> товаров

    private function groups()
    {
        /** группы товаро */
        $this->debugging('funct', __FUNCTION__ . ' ' . __LINE__, __CLASS__, '[comment]');
        $u = new DB('shop_group', 'sg');
        $u->reConnection();  // перезагрузка запроса
        if (CORE_VERSION != "5.2") {
            $u->select('sg.id, GROUP_CONCAT(sgp.name ORDER BY sgt.level SEPARATOR "/") name');
            $u->innerJoin("shop_group_tree sgt", "sg.id = sgt.id_child"); // присоединение столбца из другой таблицы
            $u->innerJoin("shop_group sgp", "sgp.id = sgt.id_parent");
            $u->orderBy('sgt.level');
        } else {
            $u->select('sg.*');
            $u->orderBy('sg.id');
        }
        $u->groupBy('sg.id');
        $groups = $u->getList();
        unset($u); // удаление переменной
        return $groups;
    } // группы товаров

    private function modifications($idsProducts)
    {
        /**
         * МОДИФИКАЦИИ ТОВАРА
         *
         * расположение : каждая модификация должна записываться отдельной строкой
         * главная стр  : при наличии модификаций - данные с главной не должны записываться
         * ассоциации   : при импорте модификаций, соотноситься должны по ключевому полю с DB (берем за константу "URL товара")
         *
         * shop_price  shop_modifications  shop_feature  shop_feature_value_list  shop_modifications_feature  shop_modifications_img
         *
         *
         *                                                             shop_price
         *                                                          <id_price - id>
         * shop_feature (в столбцы) <id_feature - id>          shop_modifications_feature  <id - id_modification>   shop_modifications_img
         *                                                       <id_modification - id>
         * shop_feature_value_list (значения) <id_value - id>      shop_modifications
         */

        $this->debugging('funct', __FUNCTION__ . ' ' . __LINE__, __CLASS__, '[comment]');
        $u = new DB('shop_modifications', 'sm');
        $u->reConnection();  // перезагрузка запроса
        // GROUP_CONCAT(CONCAT_WS('--', CONCAT_WS('#', smg.name, sf.name), sfvl.value) SEPARATOR '\n') `values`,
        $u->select('sm.id id, sm.id_mod_group idGroup, sm.id_price idProduct, sm.code article,
                sm.value price, sm.value_opt priceOpt, sm.value_opt_corp priceOptCorp,
                sm.count, smg.name nameGroup, smg.vtype typeGroup, sm.description metaDescription,
				GROUP_CONCAT(DISTINCT sf.name, "--", sfvl.value SEPARATOR "##") AS `values`,
				GROUP_CONCAT(DISTINCT si.Picture SEPARATOR ",") AS images');
        $u->where('sm.id_price IN (?)', implode(",", $idsProducts));
        $u->innerJoin('shop_modifications_group smg',   'smg.id = sm.id_mod_group');
        $u->innerJoin('shop_modifications_feature smf', 'sm.id = smf.id_modification');
        $u->innerJoin('shop_feature sf',                'sf.id = smf.id_feature');
        $u->innerJoin('shop_feature_value_list sfvl',   'smf.id_value = sfvl.id');
        $u->leftJoin('shop_modifications_img smi',      'smi.id_modification = sm.id');
        $u->leftJoin('shop_img si',                     'si.id = smi.id_img');
        $u->orderBy('sm.id_price');
        $u->groupBy('sm.id');
        $modifications = $u->getList();
        unset($u); // удаление переменной
        return $modifications;
    } // модификации товара

    private function customValues( $formData,$headerCSV, $numColumn,
                                   $goodsL, $goodsIndex)
    {
        /**
         *  @@@@@@ @@@@@@ @@     @@@@@@  | формирование, запись файла
         *  @@       @@   @@     @@      |
         *  @@@@@@   @@   @@     @@@@@@  |
         *  @@       @@   @@     @@      |
         *  @@     @@@@@@ @@@@@@ @@@@@@  |
         */

        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');
        if(count($formData) > 1) {
            $headerCSV = array();
            $numColumn = array();
            foreach($formData as $k => $v)
                if($v['checkbox'] == 'Y') {
                    array_push($headerCSV, $v['column']);
                    array_push($numColumn, $k);
                }
            $goodsLNew = array();
            foreach($goodsL as $key => $value) {
                $VColumn = 0;
                $unit    = array();
                foreach($value as $k => $v) {
                    foreach($numColumn as $vNum) {
                        if ($VColumn == $vNum) {
                            $record  = True;
                            break;
                        }
                        else $record = False;
                    }
                    if($record == True) $unit[$k] = $v;
                    $VColumn++;
                }
                if(count($unit) > 1) array_push($goodsLNew, $unit);
            }
            $goodsL        = $goodsLNew;
            $goodsIndexNew = array();
            foreach($goodsIndex as $key => $value) {
                $VColumn = 0;
                $unit    = array();
                foreach($value as $k => $v) {
                    foreach($numColumn as $vNum) {
                        if ($VColumn == $vNum) {
                            $record  = True;
                            break;
                        }
                        else $record = False;
                    }
                    if($record == True) $unit[$k] = $v;
                    $VColumn++;
                }
                if(count($unit) > 1) array_push($goodsIndexNew, $unit);
            }
            $goodsIndex = $goodsIndexNew;
            return [$goodsL, $goodsIndex];
        }
    } // замена значений на пользовательские

    private function columnLayout( $column_number, $column, $last_column )
    {
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');
        do {
            $column_name = (($t = floor($column_number / 26)) == 0 ? '' : chr(ord('A')+$t-1)).
                chr(ord('A')+floor($column_number % 26));
            array_push($column, "{$column_name}");
            $column_number++;
        } while ($column_number != $last_column);
        return $column;
    } // разметка по столбцам (координаты)

    private function recordHeaders( $writer, $headerCSV, $line )
    {
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');
        $writer[] = $headerCSV;
        $line++;
        return [$writer, $line];
    } // записываем заголовки

    private function recorRow( $writer, $goodsL, $excludingKeys, $column, $line )
    {
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');
        foreach ($goodsL as $row) {
                $out = [];
                if ($row['count'] == "-1" || (empty($row["count"]) && $row["count"] !== "0"))
                    $row["count"] = $row['presence'];
                foreach ($row as $key => $r) {
                    if (!in_array($key, $excludingKeys)) {
                        if ($key == "description" || $key == "fullDescription") {
                            $r = preg_replace('/\\\\+/', '', $r);
                            $r = preg_replace('/\r\n+/', '', $r);
                        }
                        $out[] = iconv('utf-8', 'utf-8', $r); // CP1251
                    }
                }
                // записываем данные по товарам
                $writer[] = $out;
                $line++;
        }
        return [$writer, $line];
    } // вывод товаров без модификаций

    private function mergerWithMoffification($goodsL, $modifications)
    {
        /** добавляем модификации в массив (оптимизированный)
         * 1 получаем именной массив id модификаций
         * 2 получаем именной массив товаров / получаем список товаров без модификаций
         * 3 дополнение модификаций параметрами из товаров
         * 4 слияние готовых списков модификаций и товаровБезМодификаций
         *
         * @param array $goodsL массив товаров (до добавления модификаций)
         * @param array $modifications массив данных по модификациям (для подстановки)
         * @return array $tempGoodsL массив товаров и модификаций
         */
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');

        $tempGoodsL = array();

        /** 1 */
        $idsModsName = array();
        foreach ($modifications as $i) {
            $idsModsName[$i['idProduct']] = true;
        }

        /** 2 */
        $goodsLName = array();
        $goodsWithoutModification = array();
        foreach ($goodsL as $i) {
            if (array_key_exists($i['id'], $idsModsName)) {
                $goodsLName[$i['id']] = $i;
                unset($idsModsName[$i['id']]);
            } else {
                array_push($goodsWithoutModification, $i);
                unset($idsModsName[$i['id']]);
            }
        }
        unset($goodsL); unset($idsModsName);

        /** 3 */
        foreach ($modifications as $item) {
            $product = $goodsLName[$item['idProduct']];
            /** $item['typeGroup'] : 0 - добавляет, 1 - умножает цену, 2 - заменяет */
            switch ($item['typeGroup']) {
                case 0:
                    $item['price']        = $item['price'] + $product['price'];
                    $item['priceOpt']     = $item['priceOpt'] + $product['priceOpt'];
                    $item['priceOptCorp'] = $item['priceOptCorp'] + $product['priceOptCorp'];
                    break;
                case 1:
                    $item['price']        = $item['price'] * $product['price'];
                    $item['priceOpt']     = $item['priceOpt'] * $product['priceOpt'];
                    $item['priceOptCorp'] = $item['priceOptCorp'] * $product['priceOptCorp'];
                    break;
                case 2: break;
                default: break;
            }
            unset($item['id']);

            $modFeature = explode("##", $item['values']);
            foreach ($modFeature as $kMF => $vMF) {
                $feat = explode("--", $vMF);
                $item[ $item['nameGroup'].'#'.$feat[0] ] = $feat[1];
            }
            unset($item['nameGroup']); unset($item['values']); unset($item['idProduct']); unset($item['typeGroup']);

            unset($product['idModification']); // приходят из shopPrice запроса
            unset($product['idGroup']); unset($product['presence']);

            $newItem = array_merge($product, $item);
            array_push($tempGoodsL, $newItem);

            unset($product);
        }
        unset($goodsLName); unset($modifications);

        /** 4 */
        $tempGoodsL = array_merge($goodsWithoutModification, $tempGoodsL);
        unset($goodsWithoutModification);

        return $tempGoodsL;
    } // добавляем модификации в массив

    private function assembly($pages, $filePath)
    {
        /**
         * @@@@@@@@ @@@@@@ @@     @@ @@@@@@ | методы по работе с временными файлами
         *    @@    @@     @@@   @@@ @@  @@ |
         *    @@    @@@@@@ @@ @@@ @@ @@@@@@ |
         *    @@    @@     @@  @  @@ @@     |
         *    @@    @@@@@@ @@     @@ @@     |
         */


        $this->debugging('funct', __FUNCTION__ . ' ' . __LINE__, __CLASS__, '[comment]');
        /**
         * читает объекты из файлов "goodsL1.TMP" (число - номер цикла) в директории "files/tempfiles/",
         * кол-во циклов опредеялется общим колвом циклов экспорта;
         * полученные объекты отправляет в Spout на запись файла
         */
        if (!empty($pages)) {
            /**
             * библиотека - Spout
             * https://github.com/box/spout
             *
             * $writer->addRow(['dsfsd','fgdgdf','sdfert']);              добавить строку за раз
             * $writer->addRows([['dsfsd','sdfert'],['fdgfdg','sdfd']]);  добавлять несколько строк за раз
             * $writer->openToBrowser($fileName);                         передавать данные непосредственно в браузер
             */

            $writer = WriterFactory::create(Type::XLSX);
            $writer->setTempFolder($temporaryFilePath);                   // директория хранения временных файлов
            $writer->openToFile($filePath);                               // директория сохраниния XLSX

            for ($cycleNum = 0; $cycleNum < $pages; ++$cycleNum) {
                $goodsL = $this->readTempFiles($cycleNum);
                $writer->addRows($goodsL);
            }
            unset($mainRequest);

            /**
             * сохраняем файл
             * закрываем объект записи
             */
            $writer->close();
            unset($writer);
            unset($mainRequest);
            unset($objWriter);
        }
    } // сборка файла из временных


}
