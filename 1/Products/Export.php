<?php

$zipFile = "catalog.zip";
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $zipFile . '"');
header('Content-Transfer-Encoding: binary');

// преобразование переменных запроса в переменные БД
function convertFields($str)
{
    $str = str_replace('idGroup ', 'sg.id ', $str);
    $str = str_replace('[id]', 'sp.id ', $str);
    $str = str_replace('[idGroup]', 'sg.id ', $str);
    $str = str_replace('[idCrossGroup]', 'sgp.group_id ', $str);
    $str = str_replace('[nameGroup]', 'namegroup ', $str);
    $str = str_replace('[count]', 'presence_count', $str);
    $str = str_replace('[isNew]=true', 'sp.flag_new="Y"', $str);
    $str = str_replace('[isNew]=false', 'sp.flag_new="N"', $str);
    $str = str_replace('[isHit]=true', 'sp.flag_hit="Y"', $str);
    $str = str_replace('[isHit]=false', 'sp.flag_hit="N"', $str);
    $str = str_replace('[isActive]=true', 'sp.enabled="Y"', $str);
    $str = str_replace('[isActive]=false', 'sp.enabled="N"', $str);
    $str = str_replace('[isDiscount]=true', 'sd.discount_value>0 AND sp.discount="Y"', $str);
    $str = str_replace('[isDiscount]=false', '(sd.discount_value IS NULL OR sd.discount_value=0 OR sp.discount="N")', $str);
    $str = str_replace('[isInfinitely]=true', '(sp.presence_count IS NULL OR sp.presence_count<0)', $str);

    return $str;
}

$filter = convertFields($json->filter);
$error = null;
$format = isset($json->format) ? $json->format : "csv";
if ($format == "xml") {
    $dom = new DomDocument('1.0', 'utf-8');
    $rootDOM = $dom->appendChild($dom->createElement('objects'));
}
$root = API_ROOT;
if (IS_EXT)
    $dir = '../app-data/exports';
else $dir = '../app-data/' . $json->hostname . '/exports';
if (!file_exists($root . $dir)) {
    $dirs = explode('/', $dir);
    $path = $root;
    foreach ($dirs as $d) {
        $path .= $d;
        if (!file_exists($path))
            mkdir($path, 0700);
        $path .= '/';
    }
}

$fileName = "catalog.{$format}";
$dir = $root . $dir;
if (file_exists($dir))
    foreach (glob($dir . '/*') as $file)
        unlink($file);
$fileName = $dir . "/" . $fileName;
$zipFile = $dir . "/" . $zipFile;

function createExportFileFromQuery($query, $objectsName, $objectName)
{
    GLOBAL $fileName, $dom, $rootDOM, $format;

    if ($format == "xml")
        $objectsDOM = $rootDOM->appendChild($dom->createElement($objectsName));
    $result = se_db_query($query, 0);
    if ($result) {
        $list = array();
        $header = array();
        if ($format == "csv")
            $fp = fopen($fileName, 'w');

        while ($row = se_db_fetch_assoc($result)) {
            if (!$header) {
                $header = array_keys($row);
                $headerCSV = array();
                foreach ($header as $col) {
                    $headerCSV[] = iconv('utf-8', 'CP1251', $col);
                }
                $list[] = $header;
                if ($format == "csv")
                    fputcsv($fp, $headerCSV, ";");
            }
            if ($format == "csv") {
                $out = array();
                foreach ($row as $r)
                    $out[] = iconv('utf-8', 'CP1251', $r);
                fputcsv($fp, $out, ";");
            }
            if ($format == "xml") {
                $objectDOM = $objectsDOM->appendChild($dom->createElement($objectName));
                for ($i = 0; $i < sizeof($row); $i++) {
                    $item = $objectDOM->appendChild($dom->createElement($header[$i]));
                    $item->appendChild($dom->createTextNode($row[$header[$i]]));
                }
            }
        }
        if ($format == "csv")
            fclose($fp);
    }
}

if ($format == "xml") {

    // ********************* товары **********************************************************************************
    $u = new seTable('shop_price', 'sp');
    $u->select('sp.id Id, sp.id_group IdGroup, sg.name NameGroup, sw.name NameWarehouse,
                sp.enabled IsActive, sp.code Code, sp.article Article,
                sp.name Name, sp.price Price, sp.price_opt PriceMiniWholesale,
                sp.price_opt_corp PriceWholesale, sp.measure Measurement, sp.curr Currency, sp.bonus Bonus, sp.nds Tax,
                SUM(swp.count) Count, sp.step_count StepCount, sp.presence Presence,
                sp.flag_new IsNew, sp.flag_hit IsHit, sp.id_brand IdBrand, sb.name NameBrand, sp.volume Volume,
                sp.weight Weight, sp.discount IsDiscount, sp.max_discount MaxDiscount, sp.note Description,
                sp.text FullDescription, sp.title SeoHeader, sp.keywords SeoKeywords, sp.description SeoDescription, sp.lang Language');
    $u->leftjoin('shop_group sg', 'sg.id=sp.id_group');
    $u->leftjoin('shop_brand sb', 'sb.id = sp.id_brand');
    $u->leftjoin('shop_warehouses_price swp', 'swp.id_price=sp.id');
    $u->leftjoin('shop_warehouses sw', 'sw.id=swp.id_warehouse');
    if (!empty($filter)) {
        $u->leftjoin('(SELECT sdl.id_price, MAX(sd.discount) AS `discount_value` FROM shop_discounts sd INNER JOIN
                                shop_discount_links sdl ON sd.id=sdl.discount_id AND sdl.id_price IS NOT NULL
                                GROUP BY sdl.id_price) sd', 'sd.id_price=sp.id');
        if (strpos($filter, 'spiw.id_institution') !== false)
            $u->innerjoin('shop_price_institution_view spiw', 'spiw.id_price=sp.id');
        $u->where($filter);
    }
    $u->groupby('sp.id');
    $u->orderby('sp.id');
    createExportFileFromQuery($u->getSql(), "products", "product");

    // ********************* фото товаров ******************************************************************************
    $u = new seTable('shop_img', 'si');
    $u->select('si.id Id, si.id_price IdPrice, si.picture ImageFile, si.picture_alt ImageAlt, si.sort SortIndex,
                si.default IsDefault');
    $u->innerjoin('shop_price sp', 'si.id_price=sp.id');
    if (!empty($filter)) {
        $u->leftjoin('shop_group sg', 'sg.id=sp.id_group');
        $u->where($filter);
    }
    $u->groupby('si.id');
    $u->orderby('si.id');
    createExportFileFromQuery($u->getSql(), "images", "image");

    // ********************* группы товаров ****************************************************************************
    $u = new seTable('shop_group', 'sg');
    $u->select('sg.id Id, sg.upid IdParent, sg.id_main IdShop, sg.active IsActive, sg.code_gr Code, sg.position SortIndex,
                sg.name Name, sg.picture ImageFile, sg.picture_alt ImageAlt,
                sg.commentary Description, sg.footertext FullDescription, sg.title SeoHeader, sg.keywords SeoKeywords,
                sg.description SeoDescription, sg.lang Language');
    if (!empty($filter)) {
        $u->innerjoin('shop_price sp', 'sg.id=sp.id_group');
        $u->where($filter);
    }
    $u->groupby('sg.id');
    $u->orderby('sg.id');
    createExportFileFromQuery($u->getSql(), "groups", "group");

    // ********************* бренды ************************************************************************************
    $u = new seTable('shop_brand', 'sb');
    $u->select('sb.id Id, sb.name Name, sb.code Code, sb.image ImageFile, sb.text Description, sb.title SeoHeader,
                    sb.keywords SeoKeywords, sb.description SeoDescription, sb.lang Language');
    if (!empty($filter)) {
        $u->innerjoin('shop_price sp', 'sg.id=sp.id_brand');
        $u->where($filter);
    }
    $u->groupby('sb.id');
    $u->orderby('sb.id');
    createExportFileFromQuery($u->getSql(), "brands", "brand");

    // ********************* скидки ***********************************************************************************
    $u = new seTable('shop_discounts', 'sd');
    $u->select('sd.id Id, sd.title Name, sd.step_time StepTime, sd.step_discount StepDiscount, sd.date_from DateTimeFrom,
                sd.date_to DateTimeTo, sd.week Week, sd.summ_from SumFrom,
                sd.summ_to SumTo, sd.count_from CountFrom, sd.count_to CountTo, sd.discount Discount,
                sd.type_discount TypeDiscount, sd.summ_type TypeSum');
    if (!empty($filter)) {
        $u->innerjoin('shop_discount_links sdl', 'sdl.discount_id = sd.id');
        $u->innerjoin('shop_price sp', 'sdl.id_price=sp.id');
        $u->leftjoin('shop_group sg', 'sg.id=sp.id_group');
        $u->where($filter);
    }
    $u->groupby('sd.id');
    $u->orderby('sd.id');
    createExportFileFromQuery($u->getSql(), "discounts", "discount");

    // ********************* связи скидок ******************************************************************************
    $u = new seTable('shop_discount_links', 'sdl');
    $u->select('sdl.id Id, sdl.discount_id IdDiscount, sdl.id_price IdProduct, sdl.id_group IdGroup,
                sdl.priority SortIndex, sdl.type Type');
    if (!empty($filter)) {
        $u->innerjoin('shop_discounts sd', 'sdl.discount_id = sd.id');
        $u->innerjoin('shop_price sp', 'sdl.id_price=sp.id');
        $u->leftjoin('shop_group sg', 'sg.id=sp.id_group');
        $u->where($filter);
    }
    $u->groupby('sdl.id');
    $u->orderby('sdl.id');
    createExportFileFromQuery($u->getSql(), "discounts-products", "discount-product");

    // ********************* модификации *******************************************************************************
    $u = new seTable('shop_modifications', 'sm');
    $u->select('sm.id Id, sm.id_mod_group IdGroupModification, sm.id_price IdPrice, sm.code Code, sm.value Price,
                sm.value_opt PriceMiniWholesale, sm.value_opt_corp PriceWholesale, sm.count Count, sm.sort SortIndex');
    if (!empty($filter)) {
        $u->innerjoin('shop_price sp', 'sm.id_price=sp.id');
        $u->leftjoin('shop_group sg', 'sg.id=sp.id_group');
        $u->where($filter);
    }
    $u->groupby('sm.id');
    $u->orderby('sm.id');
    createExportFileFromQuery($u->getSql(), "modifications", "modification");

    // ********************* группы модификаций ************************************************************************
    $u = new seTable('shop_modifications_group', 'smg');
    $u->select('id Id, id_main IdShop, name Name, vtype Type, sort SortIndex');
    $u->groupby('id');
    $u->orderby('id');
    createExportFileFromQuery($u->getSql(), "modificationsGroups", "modificationGroup");

    // ********************* характеристики ****************************************************************************
    $u = new seTable('shop_feature', 'sf');
    $u->select('id Id, id_feature_group IdFeatureGroup, name Name, type Type, image ImageFile, measure Measurement,
                description Description, sort SortIndex, seo IsSeo');
    $u->groupby('id');
    $u->orderby('id');
    createExportFileFromQuery($u->getSql(), "features", "feature");

    // ********************* группы характеристик **********************************************************************
    $u = new seTable('shop_feature_group', 'sfg');
    $u->select('id Id, id_main IdShop, name Name, description Description, image ImageFile, sort SortIndex');
    $u->groupby('id');
    $u->orderby('id');
    createExportFileFromQuery($u->getSql(), "featuresGroups", "featureGroup");

    // ********************* связи характеристик с группами характеристик **********************************************
    $u = new seTable('shop_group_feature', 'sgf');
    $u->select('id Id, id_group IdGroup, id_feature IdFeature, sort SortIndex');
    $u->groupby('id');
    $u->orderby('id');
    createExportFileFromQuery($u->getSql(), "featuresGroups-features", "featureGroup-feature");

    // ********************* значения модификаций и характеристик ******************************************************
    $u = new seTable('shop_modifications_feature', 'smf');
    $u->select('smf.id Id, smf.id_price IdPrice, smf.id_modification IdModification, smf.id_feature IdFeature,
                smf.id_value IdValue, smf.value_number ValueNumber, smf.value_bool ValueBool, smf.value_string ValueString,
                smf.sort SortIndex');
    if (!empty($filter)) {
        $u->innerjoin('shop_price sp', 'smf.id_price=sp.id');
        $u->leftjoin('shop_group sg', 'sg.id=sp.id_group');
        $u->where($filter);
    }
    $u->groupby('smf.id');
    $u->orderby('smf.id');
    createExportFileFromQuery($u->getSql(), "modifications-features", "modification-feature");

    // ********************* значения списочных значений модификаций и характеристик ***********************************
    $u = new seTable('shop_feature_value_list', 'sfvl');
    $u->select('id Id, id_feature IdFeature, value Value, color Color, sort SortIndex, `default` IsDefault, image ImageFile');
    $u->groupby('id');
    $u->orderby('id');
    createExportFileFromQuery($u->getSql(), "features-valueList", "feature-valueList");

    // ********************* картинки модификаций **********************************************************************
    $u = new seTable('shop_modifications_img', 'smi');
    $u->select('smi.id Id, smi.id_modification IdModification, id_img IdImage, sort SortIndex');
    $u->groupby('id');
    $u->orderby('id');
    createExportFileFromQuery($u->getSql(), "modifications-images", "modification-image");

    // ********************* архивирование *****************************************************************************
    if ($format == "xml") {
        $fileName = "catalog.xml";
        $fileName = $dir . '/' . $fileName;
        $dom->formatOutput = true;
        $dom->save($fileName);
    }

} else {
    function getGroup($groups, $idGroup)
    {
        if (!$idGroup)
            return;

        foreach ($groups as $group) {
            if ($group["id"] == $idGroup) {
                if ($group['upid'])
                    return getGroup($groups, $group['upid']) . "/" . $group["name"];
                else return $group["name"];
            }
        }
    }

    function getGroup53($groups, $idGroup)
    {
        if (!$idGroup)
            return;

        foreach ($groups as $group) {
            if ($group["id"] == $idGroup)
                return $group["name"];
        }
    }

    $limit = 1000;
    $offset = 0;

    $u = new seTable('shop_price', 'sp');
    $u->select('COUNT(*) `count`');
    $result = $u->getList();
    $count = $result[0]["count"];
    $pages = ceil($count / $limit);

    $u = new seTable('shop_price', 'sp');
    $select = 'sp.id Id, NULL Category, sp.code Code, sp.article Article,
                sp.name Name, sp.price Price, sp.curr CodeCurrency, sp.measure Measurement, sp.presence_count Count, 
                sp.presence Presence,
                sp.weight Weight, sp.volume Volume,
                GROUP_CONCAT(si.picture SEPARATOR \';\') Images,
                sp.title MetaHeader, sp.keywords MetaKeywords, sp.description MetaDescription,
                sp.note Description, sp.text FullDescription, sm.id IdModification,
                (SELECT GROUP_CONCAT(CONCAT_WS(\'#\', sf.name,
                    IF(smf.id_value IS NOT NULL, sfvl.value, CONCAT(IFNULL(smf.value_number, \'\'), IFNULL(smf.value_bool, \'\'), IFNULL(smf.value_string, \'\')))) SEPARATOR \';\') Features
                    FROM shop_modifications_feature smf
                    INNER JOIN shop_feature sf ON smf.id_feature = sf.id AND smf.id_modification IS NULL
                    LEFT JOIN shop_feature_value_list sfvl ON smf.id_value = sfvl.id
                    WHERE smf.id_price = sp.id
                    GROUP BY smf.id_price) Features';
    if (CORE_VERSION == "5.3") {
        $select .= ', spg.id_group IdGroup';
        $u->select($select);
        $u->leftjoin("shop_price_group spg", "spg.id_price = sp.id AND spg.is_main");
    } else {
        $select .= ', sp.id_group IdGroup';
        $u->select($select);
    }
    $u->leftjoin('shop_modifications sm', 'sm.id_price = sp.id');
    $u->leftjoin('shop_img si', 'si.id_price = sp.id');
    $u->orderby('sp.id');
    $u->groupby('sp.id');

    $goods = array();
    $goodsL = array();
    $goodsIndex = array();
    for ($i = 0; $i < $pages; ++$i) {
        $goodsL = array_merge($goodsL, $u->getList($offset, $limit));
        $offset += $limit;
    }
    unset($u);

    if (!$goodsL)
        exit;

    $u = new seTable('shop_feature', 'sf');
    $u->select('sf.id Id, CONCAT_WS(\'#\', smg.name, sf.name) Name');
    $u->innerjoin('shop_group_feature sgf', 'sgf.id_feature = sf.id');
    $u->innerjoin('shop_modifications_group smg', 'smg.id = sgf.id_group');
    $u->groupby('sgf.id');
    $u->orderby('sgf.sort');
    $modsCols = $u->getList();
    unset($u);

    $u = new seTable('shop_group', 'sg');
    if (CORE_VERSION == "5.3") {
        $u->select('sg.id, GROUP_CONCAT(sgp.name ORDER BY sgt.level SEPARATOR "/") name');
        $u->innerjoin("shop_group_tree sgt", "sg.id = sgt.id_child");
        $u->innerjoin("shop_group sgp", "sgp.id = sgt.id_parent");
        $u->orderby('sgt.level');
    } else {
        $u->select('sg.*');
        $u->orderby('sg.id');
    }
    $u->groupby('sg.id');
    $groups = $u->getList();
    foreach ($goodsL as &$good) {
        if (CORE_VERSION == "5.3")
            $good["Category"] = getGroup53($groups, $good["IdGroup"]);
        else $good["Category"] = getGroup($groups, $good["IdGroup"]);
    }
    unset($u);

    foreach ($goodsL as &$item) {
        foreach ($modsCols as $col)
            $item[$col['Name']] = null;
        $goodsIndex[$item["Id"]] = &$item;
    }

    $u = new seTable('shop_modifications', 'sm');
    $u->select('sm.id Id, sm.id_mod_group IdGroup, sm.id_price IdProduct, sm.code Article, sm.value Price, sm.count Count,
        smg.name NameGroup, smg.vtype TypeGroup, GROUP_CONCAT(CONCAT_WS(\'\t\', CONCAT_WS(\'#\', smg.name, sf.name), sfvl.value) SEPARATOR \'\n\') `Values`,
        si.Picture Images');
    $u->innerjoin('shop_modifications_group smg', 'smg.id = sm.id_mod_group');
    $u->innerjoin('shop_modifications_feature smf', 'sm.id = smf.id_modification');
    $u->innerjoin('shop_feature sf', 'sf.id = smf.id_feature');
    $u->innerjoin('shop_feature_value_list sfvl', 'smf.id_value = sfvl.id');
    $u->leftjoin('shop_modifications_img smi', 'smi.id_modification = sm.id');
    $u->leftjoin('shop_img si', 'si.id = smi.id_img');
    $u->orderby('sm.id_price');
    $u->groupby('sm.id');
    $modifications = $u->getList();
    unset($u);

    $excludingKeys = array("IdGroup", "Presence", "IdModification");
    $rusCols = array("Id" => "Ид.", "Article" => "Артикул", "Code" => "Код", "Name" => "Наименование",
        "Price" => "Цена", "Count" => "Кол-во", "Category" => "Категория", "Weight" => "Вес", "Volume" => "Объем",
        "Measurement" => "Ед.Изм.", "Description" => "Краткое описание", "FullDescription" => "Полное описание", "Features" => "Характеристики",
        "Images" => 'Изображения', "CodeCurrency" => "КодВалюты",
        "MetaHeader" => "MetaHeader", "MetaKeywords" => "MetaKeywords", "MetaDescription" => "MetaDescription");
    $fp = fopen($fileName, 'w');
    $header = array_keys($goodsL[0]);
    $headerCSV = array();
    foreach ($header as $col)
        if (!in_array($col, $excludingKeys)) {
            $col = iconv('utf-8', 'CP1251', $rusCols[$col] ? $rusCols[$col] : $col);
            $headerCSV[] = $col;
        }
    fputcsv($fp, $headerCSV, ";");

    $i = 0;
    $header = null;
    $lastId = null;
    $goodsItem = array();

    // вывод товаров без модификаций
    foreach ($goodsL as $row) {
        if (empty($row['IdModification'])) {
            $out = array();
            if ($row['Count'] == "-1" || (empty($row["Count"]) && $row["Count"] !== "0"))
                $row["Count"] = $row['Presence'];
            foreach ($row as $key => $r) {
                if (!in_array($key, $excludingKeys)) {
                    if ($key == "Description" || $key == "FullDescription") {
                        $r = preg_replace('/\\\\+/', '', $r);
                        $r = preg_replace('/\r\n+/', '', $r);
                    }
                    $out[] = iconv('utf-8', 'CP1251', $r);
                }
            }
            fputcsv($fp, $out, ";");
        }
    }

    // вывод товаров с модификациями
    foreach ($modifications as $mod) {
        if ($lastId != $mod["IdProduct"]) {
            $goodsItem = $goodsIndex[$mod["IdProduct"]];
            $lastId = $mod["IdProduct"];
        }
        if ($goodsItem) {
            $row = $goodsItem;
            switch ($mod['TypeGroup']) {
                case 0:
                    $row['Price'] = $row['Price'] . "+" . $mod['Price'];
                    break;
                case 1:
                    $row['Price'] = $row['Price'] . "*" . $mod['Price'];
                    break;
                case 2:
                    $row['Price'] = $mod['Price'];
                    break;
            }
            if ($mod['Count'] == "-1" || (empty($mod["Count"]) && $mod["Count"] !== "0"))
                $row["Count"] = $row['Presence'];
            else $row["Count"] = $mod['Count'];
            if (!empty($mod['Images']))
                $row['Images'] = $mod['Images'];
            if (!empty($mod['Values'])) {
                $values = explode("\n", $mod['Values']);
                foreach ($values as $val) {
                    $valCol = explode("\t", $val);
                    if (count($valCol) == 2 && !(empty($valCol[0])) && !(empty($valCol[1])))
                        $row[$valCol[0]] = $valCol[1];
                }
            }
            $out = array();
            foreach ($row as $key => $r) {
                if (!in_array($key, $excludingKeys)) {
                    if ($key == "Description" || $key == "FullDescription") {
                        $r = preg_replace('/\\\\+/', '', $r);
                        $r = preg_replace('/\r\n+/', '', $r);
                    }
                    $out[] = iconv('utf-8', 'CP1251', $r);
                }
            }
            fputcsv($fp, $out, ";");
        }
    }
    fclose($fp);
}

// подгружаем библиотеку zip
$zip = new ZipArchive();
if (file_exists($zipFile))
    unlink($zipFile);
if ($zip->open($zipFile, ZIPARCHIVE::CREATE == true))
    $zip->addFile($fileName, "catalog.{$format}");
$zip->close();

echo file_get_contents($zipFile);