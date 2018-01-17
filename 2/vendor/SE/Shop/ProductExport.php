<?php

namespace SE\Shop;
use SE\Shop\Product;
use \PHPExcel as PHPExcel;
use \PHPExcel_Writer_Excel2007 as PHPExcel_Writer_Excel2007;
use \PHPExcel_Style_Fill as PHPExcel_Style_Fill;

class ProductExport extends Product
{

    // @@@@@@ @@@@@@ @@@@@@ @@    @@ @@@@@@ @@  @@ @@@@@@
    // @@  @@ @@  @@ @@     @@    @@ @@      @@@@  @@  @@
    // @@@@@@ @@@@@@ @@@@@@  @@  @@  @@@@@@   @@   @@@@@@
    // @@     @@ @@  @@       @@@@   @@      @@@@  @@
    // @@     @@  @@ @@@@@@    @@    @@@@@@ @@  @@ @@

    // превью экспорта
    public function previewExport($limit, $offset) {
        $this->debugging('funct',__FUNCTION__.' '.__LINE__); // отладка
        // получаем данные из БД
        $u = new DB('shop_price', 'sp');
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


        $goodsL = [];
        $goodsIndex = [];
        for ($i = 0; $i < $pages; ++$i) {
            $goodsL = array_merge($goodsL, $u->getList($offset, $limit));
            $offset += $limit;
        }

        // фильтрация значений
        $goodsLFilter = [];
        foreach($goodsL as $i) {
            if($i[stepCount] == 1) {
                $i[stepCount] = '';
            }
            array_push($goodsLFilter, $i);
        }
        $goodsL = $goodsLFilter;

        unset($u); // удаление переменной

        $this->debugging('экспортируемые данные',__FUNCTION__.' '.__LINE__); // отладка
        //writeLog($goodsL);

        if (!$goodsL)
            throw new Exception();

        // особенности
        $u = new DB('shop_feature', 'sf');
        $u->select('sf.id Id, CONCAT_WS(\'#\', smg.name, sf.name) name');
        $u->innerJoin('shop_group_feature sgf', 'sgf.id_feature = sf.id');
        $u->innerJoin('shop_modifications_group smg', 'smg.id = sgf.id_group');
        $u->groupBy('sgf.id');
        $u->orderBy('sgf.sort');
        $modsCols = $u->getList();
        unset($u); // удаление переменной

        // группы товаров
        $u = new DB('shop_group', 'sg');
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
        foreach ($goodsL as &$good) {
            if (CORE_VERSION != "5.2")
                $good["category"] = parent::getGroup53($groups, $good["idGroup"]);
            else $good["category"] = parent::getGroup($groups, $good["idGroup"]);
        }
        unset($u); // удаление переменной

        foreach ($goodsL as &$item) {
            foreach ($modsCols as $col)
                $item[$col['name']] = null;
            $goodsIndex[$item["id"]] = &$item;
        }

        // модификации товара
        $u = new DB('shop_modifications', 'sm');
        $u->select('sm.id id, sm.id_mod_group idGroup, sm.id_price idProduct, sm.code article, sm.value price, sm.count,
				smg.name nameGroup, smg.vtype typeGroup,
				GROUP_CONCAT(CONCAT_WS(\'\t\', CONCAT_WS(\'#\', smg.name, sf.name), sfvl.value) SEPARATOR \'\n\') `values`,
				si.Picture images');
        $u->innerJoin('shop_modifications_group smg', 'smg.id = sm.id_mod_group');
        $u->innerJoin('shop_modifications_feature smf', 'sm.id = smf.id_modification');
        $u->innerJoin('shop_feature sf', 'sf.id = smf.id_feature');
        $u->innerJoin('shop_feature_value_list sfvl', 'smf.id_value = sfvl.id');
        $u->leftJoin('shop_modifications_img smi', 'smi.id_modification = sm.id');
        $u->leftJoin('shop_img si', 'si.id = smi.id_img');
        $u->orderBy('sm.id_price');
        $u->groupBy('sm.id');
        $modifications = $u->getList();
        unset($u); // удаление переменной

        $excludingKeys = array("idGroup", "presence", "idModification");
        $rusCols = $this->rusCols;

        $header = array_keys($goodsL[0]);
        $headerCSV = [];
        foreach ($header as $col)
            if (!in_array($col, $excludingKeys)) {
                $col = iconv('utf-8', 'utf-8', $rusCols[$col] ? $rusCols[$col] : $col); // CP1251
                $headerCSV[] = $col;
            }


        $return = array(
            'goodsL'            => $goodsL,
            'goodsIndex'        => $goodsIndex,
            'modifications'     => $modifications,
            'excludingKeys'     => $excludingKeys,
            'headerCSV'         => $headerCSV
        );
        return $return;
    }

    // @@@@@@ @@    @@ @@@@@@  @@@@@@ @@  @@ @@@@@@
    // @@     @@@   @@ @@   @@ @@      @@@@  @@  @@
    // @@@@@@ @@@@@ @@ @@   @@ @@@@@@   @@   @@@@@@
    // @@     @@  @@@@ @@   @@ @@      @@@@  @@
    // @@@@@@ @@   @@@ @@@@@@  @@@@@@ @@  @@ @@

    // завершение экспорта
    public function endExport(
        $sheet, $line, $goodsL, $goodsIndex, $xls, $modifications,
        $excludingKeys, $headerCSV, $filePath, $urlFile, $fileName,
        $formData
    ) {
        $this->debugging('funct',__FUNCTION__.' '.__LINE__); // отладка
        // определяем колво заголовков и генерируем список столбцов по длине

        // замена значений на пользовательские
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
                $unit = array();
                foreach($value as $k => $v) {
                    foreach($numColumn as $vNum) {
                        if ($VColumn == $vNum) {
                            $record = True;
                            break;
                        }
                        else $record = False;
                    }
                    if($record == True) $unit[$k] = $v;
                    $VColumn++;
                }
                if(count($unit) > 1) array_push($goodsLNew, $unit);
            }
            $goodsL = $goodsLNew;
            $goodsIndexNew = array();
            foreach($goodsIndex as $key => $value) {
                $VColumn = 0;
                $unit = array();
                foreach($value as $k => $v) {
                    foreach($numColumn as $vNum) {
                        if ($VColumn == $vNum) {
                            $record = True;
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
        }


        $column = array();
        $last_column = count($headerCSV);
        $column_number = 0;
        do {
            $column_name = (($t = floor($column_number / 26)) == 0 ? '' : chr(ord('A')+$t-1)).
                chr(ord('A')+floor($column_number % 26));
            array_push($column, "{$column_name}");
            $column_number++;
        } while ($column_number != $last_column);

        // записываем заголовки
        $column_num = 0;
        foreach($headerCSV as $head) {
            $sheet->setCellValue("{$column[$column_num]}{$line}", $head);
            $column_num++;
        }
        $line++;

        $i = 0;
        $header = null;
        $lastId = null;
        $goodsItem = [];

        // вывод товаров без модификаций
        foreach ($goodsL as $row) {
            if (empty($row['idModification'])) {
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
                $column_num = 0;
                foreach($out as $ou) {
                    $sheet->setCellValue("{$column[$column_num]}{$line}", $ou);
                    $column_num++;
                }
                $line++;
            }
        }

        // вывод товаров с модификациями
        foreach ($modifications as $mod) {
            if ($lastId != $mod["idProduct"]) {
                $goodsItem = $goodsIndex[$mod["idProduct"]];
                $lastId = $mod["idProduct"];
            }
            if ($goodsItem) {
                $row = $goodsItem;
                switch ($mod['typeGroup']) {
                    case 0:
                        $row['price'] = $row['price'] . "+" . $mod['price'];
                        break;
                    case 1:
                        $row['price'] = $row['price'] . "*" . $mod['price'];
                        break;
                    case 2:
                        $row['price'] = $mod['price'];
                        break;
                }
                if ($mod['count'] == "-1" || (empty($mod["count"]) && $mod["count"] !== "0"))
                    $row["count"] = $row['presence'];
                else $row["count"] = $mod['count'];
                if (!empty($mod['images']))
                    $row['images'] = $mod['images'];
                if (!empty($mod['values'])) {
                    $values = explode("\n", $mod['values']);
                    foreach ($values as $val) {
                        $valCol = explode("\t", $val);
                        if (count($valCol) == 2 && !(empty($valCol[0])) && !(empty($valCol[1])))
                            $row[$valCol[0]] = $valCol[1];
                    }
                }
                $out = [];
                foreach ($row as $key => $r) {
                    if (!in_array($key, $excludingKeys)) {
                        if ($key == "description" || $key == "fullDescription") {
                            $r = preg_replace('/\\\\+/', '', $r);
                            $r = preg_replace('/\r\n+/', '', $r);
                        }
                        $out[] = iconv('utf-8', 'utf-8', $r); // CP1251
                    }
                }
                // записываем данные по модификациям товаров
                $column_num = 0;
                foreach($out as $ou) {
                    $sheet->setCellValue("{$column[$column_num]}{$line}", $ou);
                    $column_num++;
                }
                $line++;
            }
        }
        // записываем в файл
        $objWriter = new PHPExcel_Writer_Excel2007($xls);
        $objWriter->save($filePath);

        return $headerCSV;

//        // передача в Ajax
//        if (file_exists($filePath) && filesize($filePath)) {
//            $this->result['url'] = $urlFile;
//            $this->result['name'] = $fileName;
//            $this->result['headerCSV'] = $headerCSV;
//        } else $this->result = "Не удаётся экспортировать данные контакта!";
    }

}
