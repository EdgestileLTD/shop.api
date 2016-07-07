<?php

$isRemoveAll = isset($_GET['isClear']) ? $_GET['isClear'] : false;
$isInsertMode = isset($_GET['isInsert']) ? $_GET['isInsert'] : false;

$root = API_ROOT;
IF (IS_EXT)
    $dir = '../app-data/imports';
else $dir = '../app-data/' . $json->hostname . '/imports';
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
$dir = $root . $dir;
if (file_exists($dir))
    foreach (glob($dir . '/*') as $file)
        unlink($file);

$zipFile = $dir . "/products.zip";

if ((!empty($_FILES["file_import"]) && !move_uploaded_file($_FILES["file_import"]['tmp_name'], $zipFile)) ||
    (!empty($_FILES["file"]) && !move_uploaded_file($_FILES["file"]['tmp_name'], $zipFile)))
    exit;

$zip = new ZipArchive();
$result = $zip->open($zipFile);
if ($result === TRUE) {
    $zip->extractTo($dir);
    $zip->close();
    unlink($zipFile);
}
$content = file_get_contents($zipFile);
if (strpos($content, "<?xml") === FALSE) {
    rename($zipFile, str_replace("products.zip", "catalog.csv", $zipFile));
    $fileName = $dir . "/catalog.csv";
} else {
    rename($zipFile, str_replace("products.zip", "catalog.xml", $zipFile));
    $fileName = $dir . "/catalog.xml";
}

$rusCols = array("Id" => "Ид.", "Article" => "Артикул", "Code" => "Код", "Name" => "Наименование",
    "Price" => "Цена", "Count" => "Кол-во", "Category" => "Категория", "Weight" => "Вес", "Volume" => "Объем",
    "Measurement" => "Ед.Изм.", "Description" => "Краткое описание", "FullDescription" => "Полное описание",
    "Features" => "Характеристики",
    "Images" => 'Изображения', "CodeCurrency" => "КодВалюты",
    "MetaHeader" => "MetaHeader", "MetaKeywords" => "MetaKeywords", "MetaDescription" => "MetaDescription");
$trCols = array_flip($rusCols);

function maxArticle($group_id)
{
    $u = new seTable('shop_price', 'sp');
    $u->select('MAX(`article` + 1) AS art');
    $result = $u->fetchOne();
    $result = (!empty($result['art'])) ? $result['art'] : $group_id . '001';
    $l = strlen($result);
    if ($l < 12)
        for ($i = 0; $i < (12 - $l); ++$i)
            $result = "0" . $result;
    return $result;
}

function getCode($code, $table, $fieldCode, $codes = null)
{
    $code_n = $code;
    $u = new seTable($table, 't');
    $i = 1;
    while ($i < 1000) {
        $u->findlist("{$fieldCode}='$code_n'")->fetchOne();
        if ($u->id || in_array($code_n, $codes))
            $code_n = $code . "-$i";
        else return $code_n;
        $i++;
    }
    return uniqid();
}

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

function createGroup(&$groups, $idParent, $name)
{
    foreach ($groups as $group) {
        if ($group['upid'] == $idParent && $group['name'] == $name)
            return $group['id'];
    }

    $u = new seTable('shop_group', 'sg');
    $u->code_gr = getCode(strtolower(se_translite_url($name)), 'shop_group', 'code_gr');
    $u->name = $name;
    if ($idParent)
        $u->upid = $idParent;
    $id = $u->save();

    $group = array();
    $group["id"] = $id;
    $group['name'] = $name;
    $group["code_gr"] = $u->code_gr;
    $group['upid'] = $idParent;
    $groups[] = $group;

    return $id;
}


function getLevel($id)
{
    global $DBH;

    $level = 0;
    $sqlLevel = 'SELECT `level` FROM shop_group_tree WHERE id_parent = :id_parent LIMIT 1';
    $sth = $DBH->prepare($sqlLevel);
    $params = array("id_parent" => $id);
    $answer = $sth->execute($params);
    if ($answer !== false) {
        $items = $sth->fetchAll(PDO::FETCH_ASSOC);
        if (count($items))
            $level = $items[0]['level'];
    }
    return $level;
}

function saveIdParent($id, $idParent)
{
    global $DBH;

    $level = 0;
    $sqlGroupTree = "INSERT INTO shop_group_tree (id_parent, id_child, `level`)
                            SELECT id_parent, :id, `level` FROM shop_group_tree
                            WHERE id_child = :id_parent
                            UNION ALL
                            SELECT :id, :id, :level";
    $sthGroupTree = $DBH->prepare($sqlGroupTree);
    if (!empty($idParent)) {
        $level = getLevel($idParent);
        $level++;
    }
    $sthGroupTree->execute(array('id_parent' => $idParent, 'id' => $id, 'level' => $level));
}

function createGroup53(&$groups, $idParent, $name)
{
    foreach ($groups as $group) {
        if ($group['upid'] == $idParent && $group['name'] == $name)
            return $group['id'];
    }

    $u = new seTable('shop_group', 'sg');
    $u->code_gr = getCode(strtolower(se_translite_url($name)), 'shop_group', 'code_gr');
    $u->name = $name;
    $id = $u->save();

    $group = array();
    $group["id"] = $id;
    $group['name'] = $name;
    $group["code_gr"] = $u->code_gr;
    $group['upid'] = $idParent;
    $groups[] = $group;

    saveIdParent($id, $idParent);

    return $id;
}

se_db_query("SET AUTOCOMMIT=0; START TRANSACTION");

try {
    if ($isRemoveAll) {
        se_db_query("SET foreign_key_checks = 0");
        se_db_query("TRUNCATE TABLE shop_group");
        se_db_query("TRUNCATE TABLE shop_price");
        se_db_query("TRUNCATE TABLE shop_brand");
        se_db_query("TRUNCATE TABLE shop_img");
        se_db_query("TRUNCATE TABLE shop_group_price");
        se_db_query("TRUNCATE TABLE shop_discounts");
        se_db_query("TRUNCATE TABLE shop_discount_links");
        se_db_query("TRUNCATE TABLE shop_modifications");
        se_db_query("TRUNCATE TABLE shop_modifications_group");
        se_db_query("TRUNCATE TABLE shop_feature_group");
        se_db_query("TRUNCATE TABLE shop_feature");
        se_db_query("TRUNCATE TABLE shop_group_feature");
        se_db_query("TRUNCATE TABLE shop_modifications_feature");
        se_db_query("TRUNCATE TABLE shop_feature_value_list");
        se_db_query("TRUNCATE TABLE shop_modifications_img");
        se_db_query("TRUNCATE TABLE shop_tovarorder");
        se_db_query("TRUNCATE TABLE shop_order");
        se_db_query("SET foreign_key_checks = 1");
    }

    function getArrayFromCsv($file)
    {
        global $trCols, $rusCols;

        $result = array();
        if (($handle = fopen($file, "r")) !== FALSE) {
            $i = 0;
            $keys = array();
            while (($row = fgetcsv($handle, 16000, ";")) !== FALSE) {
                if (!$i) {
                    foreach ($row as &$item) {
                        $item = iconv('CP1251', 'utf-8', $item);
                        if (in_array($item, $rusCols))
                            $keys[] = $trCols[$item];
                        else $keys[] = $item;
                    }
                } else {
                    $object = array();
                    $j = 0;
                    foreach ($row as &$item) {
                        $object[$keys[$j]] = iconv('CP1251', 'utf-8', $item);
                        $j++;
                    }
                    $result[] = $object;
                }
                $i++;
            }
            fclose($handle);
        }
        return $result;
    }

    $rows = getArrayFromCsv($fileName);

    $isModificationMode = false; // режим с модификациями
    $featuresCols = array();
    $featuresKeys = array();
    $modsGroupsKeys = array();
    if ($rows) {
        $cols = array_keys($rows[0]);
        foreach ($cols as $col)
            if (!in_array($col, $trCols)) {
                $featuresCols[] = $col;
                $name = explode('#', $col);
                if (count($name) == 2) {
                    if (!in_array($name[0], $modsGroupsKeys))
                        $modsGroupsKeys[$name[0]] = null;
                    if (!in_array($name[1], $featuresKeys))
                        $featuresKeys[$name[1]] = null;
                    $isModificationMode = true;
                }
            }
    }

    $lastVal = null;
    $lastRow = null;
    $goodsInsert = array();
    $goodsUpdate = array();
    $groupsKeys = array();
    $featureValuesKeys = array();
    $groupTypesMods = array();
    $i = 0;
    foreach ($rows as &$row) {
        $mods = array();
        $mods['Article'] = $row['Article'];
        $mods['Price'] = $row['Price'];
        $mods['Count'] = $row['Count'];
        $mods['Images'] = $row['Images'];
        $mods['Type'] = 0;
        if ($isModificationMode)
            foreach ($featuresCols as $col) {
                $cols = explode('#', $col);
                if (count($cols) == 2) {
                    $mods['GroupModifications'] = $cols[0];
                    $groupTypesMods[$cols[0]][$cols[1]] = null;
                }
                if (count($cols) == 2 && !empty($row[$col])) {
                    $mods["Features"][$cols[1]] = $row[$col];
                    $featureValuesKeys[$cols[1]][$row[$col]] = null;
                }
            }
        if ((!empty($row['Id']) && $row['Id'] != $lastVal) ||
            (empty($row['Id']) && !empty($row['Name']) && $row['Name'] != $lastVal)) {
            foreach ($featuresCols as $col)
                unset($row[$col]);
            if (!$isInsertMode)
                $goodsUpdate[] = &$row;
            else $goodsInsert[] = &$row;
            $lastRow = &$row;
            $lastVal = !empty($row['Id']) ? $row['Id'] : $row['Name'];
            if (!empty($row['Category']))
                $groupsKeys[$row['Category']] = null;
            if (!empty($row['Features'])) {
                $features = explode(';', $row['Features']);
                foreach ($features as $feature) {
                    $f = explode('#', $feature);
                    if (count($f) == 2) {
                        $featureName = $f[0];
                        $featureValue = $f[1];
                        if (!in_array($featureName, $featuresKeys))
                            $featuresKeys[$featureName] = null;
                        if (!empty($featureValue))
                            $featureValuesKeys[$featureName][$featureValue] = null;
                    }
                }
            }
        }
        if ($isModificationMode)
            $lastRow['Modifications'][] = $mods;
    }

    // добавление товаров
    if ($goodsInsert) {
        // добавление группы товаров
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
        foreach ($groups as $group) {
            if (CORE_VERSION == "5.3")
                $path = getGroup53($groups, $group['id']);
            else $path = getGroup($groups, $group['id']);
            if ($path)
                $groupsKeys[$path] = $group['id'];
        }
        foreach ($groupsKeys as $key => $value) {
            if (!$value) {
                $names = explode("/", $key);
                $idParent = null;
                foreach ($names as $name) {
                    if (CORE_VERSION == "5.3")
                        $idParent = createGroup53($groups, $idParent, $name);
                    else $idParent = createGroup($groups, $idParent, $name);
                }
                $groupsKeys[$key] = $idParent;
            }
        }

        // добавление группы модификации
        $newModsGroupsKeys = array();
        if ($isModificationMode && $modsGroupsKeys) {
            $u = new seTable('shop_modifications_group', 'smg');
            $u->select('id, name');
            $u->orderby('id');
            $modsGroups = $u->getList();
            $id = 0;
            foreach ($modsGroups as $modGroup) {
                $modsGroupsKeys[$modGroup['name']] = $modGroup['id'];
                $id = $id < $modGroup['id'] ? $modGroup['id'] + 1 : $id;
            }
            foreach ($modsGroupsKeys as $key => $value) {
                if (empty($value))
                    $dataModsGroups[] = array('id' => $value = ++$id, 'name' => $key);
                $newModsGroupsKeys[$key] = $value;
            }
            if (!empty($dataModsGroups))
                se_db_InsertList('shop_modifications_group', $dataModsGroups);
            unset($modsGroupsKeys);
            unset($dataModsGroups);
        }

        // добавление параметров для модификаций
        $newFeaturesKeys = array();
        if ($featuresKeys) {
            $u = new seTable('shop_feature', 'sf');
            $u->select('id, name, type');
            $u->orderby('id');
            $features = $u->getList();
            $id = 0;
            foreach ($features as $feature) {
                $featuresKeys[$feature['name']] = $feature['id'];
                $id = $id < $feature['id'] ? $feature['id'] + 1 : $id;
            }
            foreach ($featuresKeys as $key => $value) {
                if (empty($value))
                    $dataFeatures[] = array('id' => $value = ++$id, 'name' => $key, 'type' => 'list');
                $newFeaturesKeys[$key] = $value;
            }

            if (!empty($dataFeatures))
                se_db_InsertList('shop_feature', $dataFeatures);
            unset($featuresKeys);
            unset($dataFeatures);
        }

        // добавление значений для параметров
        $newValuesKeys = array();
        if ($featureValuesKeys) {
            $u = new seTable('shop_feature_value_list', 'sfvl');
            $u->select('sfvl.id, sfvl.value, sf.name feature');
            $u->innerjoin('shop_feature sf', 'sf.id = sfvl.id_feature');
            $u->orderby('id');
            $values = $u->getList();
            $id = 0;
            foreach ($values as $value) {
                $featureValuesKeys[$value['feature']][$value['value']] = $value['id'];
                $id = $id < $value['id'] ? $value['id'] + 1 : $id;
            }
            foreach ($featureValuesKeys as $key => $val) {
                $idFeature = array_key_exists($key, $newFeaturesKeys) ? $newFeaturesKeys[$key] : null;
                foreach ($val as $k => $v) {
                    if (!empty($idFeature) && empty($v))
                        $dataFeaturesValues[] = array('id' => $v = ++$id, 'id_feature' => $idFeature, 'value' => $k);
                    $newValuesKeys[$key][$k] = $v;
                }
            }
            if (!empty($dataFeaturesValues))
                se_db_InsertList('shop_feature_value_list', $dataFeaturesValues);
            unset($dataFeaturesValues);
            unset($featureValuesKeys);
        }

        // объединение модификаций в группу (shop_group_feature)
        if ($isModificationMode && $groupTypesMods) {
            $u = new seTable('shop_group_feature', 'sgf');
            $u->select('sgf.id, sf.name feature, smg.name `group`');
            $u->innerjoin('shop_feature sf', 'sf.id = sgf.id_feature');
            $u->innerjoin('shop_modifications_group smg', 'smg.id = sgf.id_group');
            $u->orderby('id');
            $rows = $u->getList();
            foreach ($rows as $row)
                $groupTypesMods[$row['group']][$row['feature']] = $row['id'];
            foreach ($groupTypesMods as $key => $value) {
                $idGroup = array_key_exists($key, $newModsGroupsKeys) ? $newModsGroupsKeys[$key] : null;
                foreach ($value as $k => $v) {
                    $idFeature = array_key_exists($k, $newFeaturesKeys) ? $newFeaturesKeys[$k] : null;
                    if (!empty($idGroup) && !empty($idFeature) && empty($v))
                        $dataTypesMods[] = array('id_feature' => $idFeature, 'id_group' => $idGroup);
                }
            }
            if (!empty($dataTypesMods))
                se_db_InsertList('shop_group_feature', $dataTypesMods);
        }

        // добавление товаров
        $u = new seTable('shop_price', 'sp');
        $u->select('MAX(id) maxId');
        $u->fetchOne();
        $idProduct = $u->maxId;
        $u = new seTable('shop_modifications', 'sm');
        $u->select('MAX(id) maxId');
        $u->fetchOne();
        $idModification = $u->maxId;
        $dataGoodsGroups = array();
        $rowInsert = 0;
        $rowCount = 0;
        $countGoods = count($goodsInsert);
        $codes = array();
        foreach ($goodsInsert as &$goodsItem) {
            $idProduct++;
            $images = !empty($goodsItem['Images']) ? explode(";", $goodsItem['Images']) : array();
            $goodsItem['IdGroup'] = $IdGroup = !empty($goodsItem['Category']) ? $groupsKeys[$goodsItem['Category']] : null;
            if (empty($IdGroup))
                $IdGroup = 'null';
            if (empty($goodsItem['Code']))
                $goodsItem['Code'] = strtolower(se_translite_url($goodsItem['Name']));
            $goodsItem['Code'] = getCode($goodsItem['Code'], 'shop_price', 'code', $codes);
            $codes[] = $goodsItem['Code'];
            if (empty($goodsItem['Article']))
                $goodsItem['Article'] = maxArticle($goodsItem['IdGroup']);
            $price = $goodsItem['Price'];
            if (($ind = strpos($price, '+')) || ($ind = strpos($price, '*')))
                $price = substr($price, 0, $ind - 1);
            $count = $goodsItem['Count'];
            if ($isModificationMode) {
                $count = empty($goodsItem['Modifications']) ? $goodsItem['Count'] : null;
                if (!empty($goodsItem['Modifications'])) {
                    foreach ($goodsItem['Modifications'] as $mod) {
                        if ($mod['Count'] > 0)
                            $count += $mod['Count'];
                        $codeM = empty($mod['Article']) ? $goodsItem['Article'] : $mod['Article'];
                        $valueM = !empty($mod['Price']) ? $mod['Price'] : 'null';
                        if (($ind = strpos($valueM, '+')) || ($ind = strpos($valueM, '*')))
                            $valueM = substr($valueM, $ind + 1, strlen($valueM) - $ind);
                        $countM = !empty($mod['Count']) || ($mod['Count'] == '0.000') ? $mod['Count'] : 'null';
                        $idModGroup = !empty($mod['GroupModifications']) ? $newModsGroupsKeys[$mod['GroupModifications']] : null;
                        if ($idModGroup) {
                            $dataModifications[] = array("id" => ++$idModification, "id_mod_group" => $idModGroup,
                                "id_price" => $idProduct, 'code' => $codeM,
                                'value' => $valueM, 'count' => $countM);
                            if (!empty($mod['Features'])) {
                                $featuresM = $mod['Features'];
                                foreach ($featuresM as $key => $val) {
                                    $idFeature = array_key_exists($key, $newFeaturesKeys) ? $newFeaturesKeys[$key] : null;
                                    if (!$idFeature)
                                        continue;
                                    $idValue = $newValuesKeys[$key][$val];
                                    if (!$idValue)
                                        continue;
                                    $dataModFeatures[] = array("id_price" => $idProduct, 'id_modification' => $idModification,
                                        'id_feature' => $idFeature, 'id_value' => $idValue);
                                }
                            }
                        }
                        $images = array_merge($images, !empty($mod['Images']) ? explode(";", $mod['Images']) : array());
                    }
                }
            }
            if (!empty($goodsItem['Features'])) {
                $features = explode(';', $goodsItem['Features']);
                foreach ($features as $feature) {
                    $f = explode('#', $feature);
                    if (count($f) == 2) {
                        $featureName = $f[0];
                        $featureValue = $f[1];
                        $idFeature = array_key_exists($featureName, $newFeaturesKeys) ? $newFeaturesKeys[$featureName] : null;
                        if (!$idFeature)
                            continue;
                        $idValue = $newValuesKeys[$featureName][$featureValue];
                        if (!$idValue)
                            continue;
                        $dataModFeatures[] = array("id_price" => $idProduct, 'id_feature' => $idFeature, 'id_value' => $idValue);
                    }
                }
            }
            $images = array_unique($images);
            if (empty($count) && $count != "0.000")
                $count = -1;
            $measure = !empty($goodsItem['Measurement']) ? $goodsItem['Measurement'] : 'null';
            $weight = !empty($goodsItem['Weight']) ? $goodsItem['Weight'] : 'null';
            $volume = !empty($goodsItem['Volume']) ? $goodsItem['Volume'] : 'null';
            $description = !empty($goodsItem['Description']) ? $goodsItem['Description'] : 'null';
            $fullDescription = !empty($goodsItem['FullDescription']) ? $goodsItem['FullDescription'] : 'null';
            $codeCurrency = !empty($goodsItem['CodeCurrency']) ? $goodsItem['CodeCurrency'] : 'RUB';
            $metaHeader = !empty($goodsItem['MetaHeader']) ? $goodsItem['MetaHeader'] : 'null';
            $metaKeywords = !empty($goodsItem['MetaKeywords']) ? $goodsItem['MetaKeywords'] : 'null';
            $metaDescription = !empty($goodsItem['MetaDescription']) ? $goodsItem['MetaDescription'] : 'null';
            if (CORE_VERSION == "5.3" && $goodsItem['IdGroup'])
                $dataGoodsGroups[] = array("id_group" => $goodsItem['IdGroup'], "id_price" => $idProduct, "is_main" => 1);
            $dataGoods[] = array("id" => $idProduct, "code" => $goodsItem['Code'], "article" => $goodsItem['Article'],
                "id_group" => $IdGroup, "name" => $goodsItem['Name'], 'price' => $price, 'presence_count' => $count,
                'text' => $fullDescription, 'note' => $description, 'measure' => $measure, 'weight' => $weight,
                'volume' => $volume, 'curr' => $codeCurrency, "title" => $metaHeader, "keywords" => $metaKeywords,
                "description" => $metaDescription);
            $i = 0;
            foreach ($images as $image) {
                $dataImages[] = array("id_price" => $idProduct, "picture" => $image, "default" => !$i);
                $i++;
            }

            ++$rowCount;
            if (++$rowInsert == 500 || ($rowCount >= $countGoods)) {
                if (!empty($dataGoods)) {
                    se_db_InsertList('shop_price', $dataGoods);
                    $dataGoods = null;
                }
                if (!empty($dataImages)) {
                    se_db_InsertList('shop_img', $dataImages);
                    $dataImages = null;
                }
                if (!empty($dataModifications)) {
                    se_db_InsertList('shop_modifications', $dataModifications);
                    $dataModifications = null;
                }
                if (!empty($dataModFeatures)) {
                    se_db_InsertList('shop_modifications_feature', $dataModFeatures);
                    $dataModFeatures = null;
                }
                if (!empty($dataGoodsGroups)) {
                    se_db_InsertList('shop_price_group', $dataGoodsGroups);
                    $dataGoodsGroups = null;
                }
                $rowInsert = 0;
            }
        }
    }

    // обновление товаров
    if ($goodsUpdate) {
        $sql = null;
        foreach ($goodsUpdate as $goodsItem) {
            $sqlItem = 'UPDATE shop_price SET ';
            $fields = array();
            if (!empty($goodsItem['Code']))
                $fields[] = "code = '{$goodsItem['Code']}'";
            if (!empty($goodsItem['Article']))
                $fields[] = "article = '{$goodsItem['Article']}'";
            if (!empty($goodsItem['Name']))
                $fields[] = "name = '{$goodsItem['Name']}'";
            if (!empty($goodsItem['Price'])) {
                $price = $goodsItem['Price'];
                if (($ind = strpos($price, '+')) || ($ind = strpos($price, '*')))
                    $price = substr($price, 0, $ind - 1);
                $fields[] = "price = '{$price}'";
            }
            if (!empty($goodsItem['CodeCurrency']))
                $fields[] = "curr = '{$goodsItem['CodeCurrency']}'";
            if (!empty($goodsItem['Count']))
                $fields[] = "presence_count = '{$goodsItem['Count']}'";
            if (!empty($goodsItem['Measurement']))
                $fields[] = "measure = '{$goodsItem['Measurement']}'";
            if (!empty($goodsItem['Weight']))
                $fields[] = "weight = '{$goodsItem['Weight']}'";
            if (!empty($goodsItem['Volume']))
                $fields[] = "volume = '{$goodsItem['Volume']}'";
            if (!empty($goodsItem['Description']))
                $fields[] = "note = '{$goodsItem['Description']}'";
            if (!empty($goodsItem['FullDescription']))
                $fields[] = "text = '{$goodsItem['FullDescription']}'";
            if (!empty($goodsItem['MetaHeader']))
                $fields[] = "title = '{$goodsItem['MetaHeader']}'";
            if (!empty($goodsItem['MetaKeywords']))
                $fields[] = "keywords = '{$goodsItem['MetaKeywords']}'";
            if (!empty($goodsItem['MetaDescription']))
                $fields[] = "description = '{$goodsItem['MetaDescription']}'";
            $sqlItem .= implode(",", $fields);
            $sqlItem .= ' WHERE id = ' . $goodsItem['Id'] . ';';
            $sql .= $sqlItem . "\n";
        }
        if ($sql)
            mysqli_multi_query($db_link, $sql);
    }


    se_db_query("COMMIT");
    echo "ok";

} catch (Exception $e) {
    se_db_query("ROLLBACK");
    echo "Ошибка импорта данных!";
}