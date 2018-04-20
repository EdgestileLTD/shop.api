<?php

class seTableEx extends seTable
{
    function getUpdate()
    {
        return $this->update;
    }

    function getTableName()
    {
        return $this->table_name;
    }
}

function se_db_query_ex($sql, $link = 'db_link')
{
    global $$link;

    return @mysqli_query($$link, $sql);
}

// преоброзование перемнных запроса в перемнные БД
function convertFields($str)
{
    $str = str_replace('idGroup ', 'sg.id ', $str);
    $str = str_replace('[id]', 'sp.id ', $str);
    $str = str_replace('[idGroup]', 'sg.id ', $str);
    $str = str_replace('[idCrossGroup]', 'sgp.group_id ', $str);
    $str = str_replace('[idLinkGroup]', 'scg.id ', $str);
    $str = str_replace('[nameGroup]', 'namegroup ', $str);
    $str = str_replace('[count]', 'presence_count', $str);
    $str = str_replace('[weight]', 'sp.weight', $str);
    $str = str_replace('[volume]', 'sp.volume', $str);
    $str = str_replace('[isNew]=true', 'sp.flag_new="Y"', $str);
    $str = str_replace('[isNew]=false', 'sp.flag_new="N"', $str);
    $str = str_replace('[isHit]=true', 'sp.flag_hit="Y"', $str);
    $str = str_replace('[isHit]=false', 'sp.flag_hit="N"', $str);
    $str = str_replace('[isActive]=true', 'sp.enabled="Y"', $str);
    $str = str_replace('[isActive]=false', 'sp.enabled<>"Y"', $str);
    $str = str_replace('[isDiscount]=true', 'sdl.id>0 AND sp.discount="Y"', $str);
    $str = str_replace('[isDiscount]=false', '(sdl.id IS NULL OR sp.discount="N")', $str);
    $str = str_replace('[isInfinitely]=true', '(sp.presence_count IS NULL OR sp.presence_count<0)', $str);
    $str = str_replace('[isYAMarket]=true', 'sp.is_market=1', $str);
    $str = str_replace('[idBrand]', 'sb.id', $str);
    $str = str_replace('[brand]', 'sb.name', $str);
    $str = str_replace('[idModificationGroup]', 'smg.id', $str);

    return $str;
}

$newImages = array();
if (isset($json->isCloneMode) && $json->isCloneMode) {
    $json->ids = null;
    $json->id = null;
    foreach ($json->images as $image)
        $image->id = null;
    foreach ($json->modifications as &$mod)
        foreach ($mod->items as &$item)
            $item->id = null;
}


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

function checkArticle($id, $article)
{
    $u = new seTable('shop_price', 'sp');
    $u->select('id');
    $u->where("article='$article'");
    $result = $u->getList();

    if (!empty($result))
        foreach ($result as $item) {
            if ($item['id'] != $id) {
                $status['status'] = 'error';
                $status['error'] = 'Товар с артикулом: ' . $article . ' уже существует!';
                outputData($status);
                exit;
            }
        }
}

function saveImages($idsProduct, $images)
{
    GLOBAL $newImages;
    // обновление изображений
    $idsStore = "";
    foreach ($images as $image) {
        if ($image->id > 0) {
            if (!empty($idsStore))
                $idsStore .= ",";
            $idsStore .= $image->id;
            $u = new seTable('shop_img', 'si');
            $isUpdated = false;
            $isUpdated |= setField(false, $u, $image->imageFile, 'picture');
            $isUpdated |= setField(false, $u, $image->sortIndex, 'sort');
            $isUpdated |= setField(false, $u, $image->imageAlt, 'picture_alt');
            $isUpdated |= setField(false, $u, $image->isMain, '`default`');
            $u->where('id=?', $image->id);
            if ($isUpdated)
                $u->save();
        }
    }

    $idsStr = implode(",", $idsProduct);
    if (!empty($idsStore)) {
        $u = new seTable('shop_img', 'si');
        $u->where("id_price in ($idsStr) AND NOT (id in (?))", $idsStore)->deletelist();
    } else {
        $u = new seTable('shop_img', 'si');
        $u->where('id_price in (?)', $idsStr)->deletelist();
    }

    $data = array();
    foreach ($images as $image)
        if (empty($image->id) || ($image->id <= 0)) {
            foreach ($idsProduct as $idProduct) {
                $data[] = array('id_price' => $idProduct, 'picture' => $image->imageFile,
                    'sort' => $image->sortIndex, 'picture_alt' => $image->imageAlt,
                    'default' => (int)$image->isMain);
                $newImages[] = $image->imageFile;
            }
        }

    if (!empty($data))
        se_db_InsertList('shop_img', $data);
}

function getIdSpecificationGroup($name)
{
    if (empty($name))
        return null;

    $u = new seTable('shop_feature_group', 'sfg');
    $u->select('id');
    $u->where('name = "?"', $name);
    $result = $u->fetchOne();
    if (!empty($result["id"]))
        return $result["id"];

    $u->name = $name;
    return $u->save();
}

function getIdFeature($idGroup, $name)
{
    $u = new seTable('shop_feature', 'sf');
    $u->select('id');
    $u->where('name = "?"', $name);
    if ($idGroup)
        $u->andWhere('id_feature_group = ?', $idGroup);
    else $u->andWhere('id_feature_group IS NULL');
    $result = $u->fetchOne();
    if (!empty($result["id"]))
        return $result["id"];

    if ($idGroup)
        $u->id_feature_group = $idGroup;
    $u->name = $name;
    $id = $u->save();
    return $id;
}

function getSpecificationByName($specification)
{
    $idGroup = getIdSpecificationGroup($specification->nameGroup);
    $specification->idFeature = getIdFeature($idGroup, $specification->name);
    return $specification;
}

function saveSpecifications($idsProducts, $specifications, $isAddSpecifications)
{
    $idsStr = implode(",", $idsProducts);
    if (!$isAddSpecifications) {
        $u = new seTable('shop_modifications_feature', 'smf');
        $u->where('id_modification is null and id_price in (?)', $idsStr)->deletelist();
    }

    $m = new seTable('shop_modifications_feature', 'smf');
    $m->select('id');
    foreach ($specifications as $specification) {
        $idValue = 'null';
        if (empty($specification->idFeature) && !empty($specification->name))
            $specification = getSpecificationByName($specification);
        $specification->valueNumber = 'null';
        $specification->valueBool = 'null';
        $specification->valueString = 'null';
        if ($specification->valueIdList)
            $idValue = $specification->valueIdList;
        if (!empty($specification->idValue))
            $idValue = $specification->idValue;
        switch ($specification->valueType) {
            case "S":
                $specification->valueString = $specification->value;
                break;
            case "D":
                $specification->valueNumber = (real)$specification->value;
                break;
            case "B":
                $specification->valueBool = $specification->value == "1";
                break;
        }
        if (!empty($specification->value) && is_string($specification->value))
            $specification->value = trim($specification->value);
        if (($specification->valueType == "L" || $specification->valueType == "CL" || empty($specification->valueType)) &&
            (!empty($specification->value) || is_numeric($specification->value))
        ) {
            $idFeature = $specification->idFeature;
            $u = new seTable('shop_feature_value_list', 'sfl');
            $u->select('id');
            $u->where('value = "?"', se_db_input($specification->value));
            $u->andWhere('id_feature = ?', $idFeature);
            $result = $u->fetchOne();
            if (!empty($result["id"]))
                $idValue = $result["id"];
            else {
                $u = new seTable('shop_feature_value_list', 'sfl');
                $u->select('MAX(sfl.sort) AS sort');
                $u->where('id_feature=?', $idFeature);
                $u->fetchOne();
                $sortIndex = $u->sort + 1;
                $u = new seTable('shop_feature_value_list', 'sfl');
                setField(1, $u, $specification->value, 'value');
                setField(1, $u, $idFeature, 'id_feature');
                setField(1, $u, $sortIndex, 'sort');
                $idValue = $u->save();
            }
        }
        foreach ($idsProducts as $idProduct) {
            if ($isAddSpecifications) {
                if (is_string($specification->valueString))
                    $m->where("id_price = {$idProduct} AND id_feature = {$specification->idFeature} AND value_string = '{$specification->valueString}'");
                if (is_bool($specification->valueBool))
                    $m->where("id_price = {$idProduct} AND id_feature = {$specification->idFeature} AND value_bool = '{$specification->valueBool}'");
                if (is_numeric($specification->valueNumber))
                    $m->where("id_price = {$idProduct} AND id_feature = {$specification->idFeature} AND value_number = '{$specification->valueNumber}'");
                if ($idValue)
                    $m->where("id_price = {$idProduct} AND id_feature = {$specification->idFeature} AND id_value = {$idValue}");
                $result = $m->fetchOne();
                if ($result["id"])
                    continue;
            }
            $data[] = array('id_price' => $idProduct, 'id_feature' => $specification->idFeature,
                'id_value' => $idValue,
                'value_number' => $specification->valueNumber,
                'value_bool' => $specification->valueBool, 'value_string' => $specification->valueString);
        }
    }
    if (!empty($data))
        se_db_InsertList('shop_modifications_feature', $data);
}

function saveModifications($idsProducts, $modifications)
{
    GLOBAL $newImages, $json;

    $idsStr = implode(",", $idsProducts);
    $isMultiMode = sizeof($idsProducts) > 1;
    $article = $json->article;

    $namesToIds = array();
    if (!empty($newImages)) {
        $imagesStr = '';
        foreach ($newImages AS $image) {
            if (!empty($imagesStr))
                $imagesStr .= ',';
            $imagesStr .= "'$image'";
        }
        $u = new seTable('shop_img', 'si');
        $u->select('id, picture');
        $u->where('picture IN (?)', $imagesStr);
        $u->andwhere('id_price IN (?)', $idsStr);
        $objects = $u->getList();
        foreach ($objects as $item)
            $namesToIds[$item['picture']] = $item['id'];
    }

    if (!$isMultiMode) {
        $idsUpdateM = null;
        foreach ($modifications as $mod) {
            foreach ($mod->items as $item) {
                if (!empty($item->id)) {
                    if (!empty($idsUpdateM))
                        $idsUpdateM .= ',';
                    $idsUpdateM .= $item->id;
                }
            }
        }
    }

    $u = new seTable('shop_modifications', 'sm');
    if (!empty($idsUpdateM))
        $u->where("NOT id IN ($idsUpdateM) AND id_price in (?)", $idsStr)->deletelist();
    else $u->where("id_price in (?)", $idsStr)->deletelist();


    // новые модификации
    $dataM = array();
    $dataF = array();
    $dataI = array();
    $i = se_db_insert_id('shop_modifications');
    foreach ($modifications as $mod) {
        foreach ($mod->items as $item) {
            if (empty($item->id) || $isMultiMode) {
                $count = 'null';
                if ($item->count >= 0)
                    $count = $item->count;
                foreach ($idsProducts as $idProduct) {
                    $i++;
                    if (!$isMultiMode && $article && !$item->article)
                        $item->article = $article;
                    $dataM[] = array('id' => $i, '`code`' => $item->article, 'id_mod_group' => $mod->id, 'id_price' => $idProduct, 'value' => $item->price,
                        'value_opt' => $item->priceSmallOpt, 'value_opt_corp' => $item->priceOpt,
                        'value_purchase' => (real)$item->pricePurchase, 'count' => $count,
                        'sort' => $item->sortIndex, 'description' => $item->description);
                    foreach ($item->values as $v)
                        $dataF[] = array('id_price' => $idProduct, 'id_modification' => $i,
                            'id_feature' => $v->idFeature, 'id_value' => $v->id);
                    foreach ($item->images as $img) {
                        if ($img->id <= 0)
                            $img->id = $namesToIds[$img->imageFile];
                        $dataI[] = array('id_modification' => $i, 'id_img' => $img->id,
                            'sort' => $img->sortIndex);
                    }
                }
            }
        }
    }
    if (!empty($dataM)) {
        se_db_InsertList('shop_modifications', $dataM);
        if (!empty($dataF))
            se_db_InsertList('shop_modifications_feature', $dataF);
        if (!empty($dataI))
            se_db_InsertList('shop_modifications_img', $dataI);
        $dataI = null;
    }

    // обновление модификаций
    if (!$isMultiMode) {
        foreach ($modifications as $mod) {
            foreach ($mod->items as $item) {
                if (!empty($item->id)) {
                    $u = new seTable('shop_modifications', 'sm');
                    $isUpdated = false;
                    $isUpdated |= setField(0, $u, $item->article, 'code');
                    $isUpdated |= setField(0, $u, $item->price, 'value');
                    $isUpdated |= setField(0, $u, $item->priceSmallOpt, 'value_opt');
                    $isUpdated |= setField(0, $u, $item->priceOpt, 'value_opt_corp');
                    $isUpdated |= setField(0, $u, $item->pricePurchase, 'value_purchase');
                    $isUpdated |= setField(0, $u, $item->description, 'description');
                    $count = -1;
                    if ($item->count >= 0)
                        $count = $item->count;
                    $isUpdated |= setField(0, $u, $count, 'count');
                    $isUpdated |= setField(0, $u, $item->sortIndex, 'sort');
                    if ($isUpdated) {
                        $u->where('id=(?)', $item->id);
                        $u->save();
                    }

                    $u = new seTable('shop_modifications_img', 'smi');
                    $u->where("id_modification=?", $item->id)->deletelist();
                    $dataI = array();
                    foreach ($item->images as $img) {
                        if ($img->id <= 0)
                            $img->id = $namesToIds[$img->imageFile];
                        $dataI[] = array('id_modification' => $item->id, 'id_img' => $img->id,
                            'sort' => $img->sortIndex);
                    }
                    if (!empty($dataI))
                        se_db_InsertList('shop_modifications_img', $dataI);
                }

            }
        }
    }
}

function saveSimilarProducts($idsProducts, $products)
{
    $idsExists = array();
    foreach ($products as $p)
        if ($p->id)
            $idsExists[] = $p->id;
    $idsExists = array_diff($idsExists, $idsProducts);
    $idsExistsStr = implode(",", $idsExists);
    $idsStr = implode(",", $idsProducts);
    $u = new seTable('shop_sameprice', 'ss');
    if ($idsExistsStr)
        $u->where("((NOT id_acc IN ({$idsExistsStr})) AND id_price IN (?)) OR ((NOT id_price IN ({$idsExistsStr})) AND id_acc IN (?))", $idsStr)->deleteList();
    else $u->where('id_price IN (?) OR id_acc IN (?)', $idsStr)->deleteList();
    $idsExists = array();
    if ($idsExistsStr) {
        $u->select("id_price, id_acc");
        $u->where("((id_acc IN ({$idsExistsStr})) AND id_price IN (?)) OR ((id_price IN ({$idsExistsStr})) AND id_acc IN (?))", $idsStr);
        $objects = $u->getList();
        foreach ($objects as $item) {
            $idsExists[] = $item["id_acc"];
            $idsExists[] = $item["id_price"];
        }
    };
    $data = array();
    foreach ($products as $p)
        if (empty($idsExists) || !in_array($p->id, $idsExists))
            foreach ($idsProducts as $idProduct)
                $data[] = array('id_price' => $idProduct, 'id_acc' => $p->id);
    if (!empty($data))
        se_db_InsertList('shop_sameprice', $data);
}

function saveAccompanyingProducts($idsProducts, $products)
{
    $idsStr = implode(",", $idsProducts);

    $u = new seTable('shop_accomp', 'sa');
    $u->where('id_price in (?)', $idsStr)->deletelist();

    foreach ($products as $p)
        foreach ($idsProducts as $idProduct) {
            $t = new seTable("shop_accomp");
            $t->id_price = $idProduct;
            if ($p->id) {
                $t->id_acc = $p->id;
            }
            if ($p->idGroup)
                $t->id_group = $p->idGroup;
            $t->save();
        }
}

function saveComments($idsProducts, $comments)
{

    $idsStr = implode(",", $idsProducts);
    $u = new seTable('shop_comm', 'sc');
    $u->where('id_price in (?)', $idsStr)->deletelist();

    foreach ($comments as $c) {
        $showing = 'N';
        $isActive = 'N';
        if ($c->isShowing)
            $showing = 'Y';
        if ($c->isActive)
            $isActive = 'Y';
        foreach ($idsProducts as $idProduct)
            $data[] = array('id_price' => $idProduct, 'date' => $c->date, 'name' => $c->contactTitle,
                'email' => $c->contactEmail, 'commentary' => $c->commentary, 'response' => $c->response,
                'showing' => $showing, 'is_active' => $isActive);
    }
    if (!empty($data))
        se_db_InsertList('shop_comm', $data);
}

function saveCrossGroups($idsProducts, $groups, $filter, $search)
{

    $idsStr = implode(",", $idsProducts);

    if (CORE_VERSION != "5.2") {

        if ($idsProducts[0] == '*') {

            $idsGroups = array();
            foreach ($groups as $group)
                $idsGroups[] = $group->id;

            $idsGroups = implode(",", $idsGroups);
            $sqlDelete = 'DELETE FROM shop_price_group WHERE NOT is_main';
            if ($idsGroups)
                $sqlDelete .= " AND NOT id_group IN ($idsGroups)";


            $searchStr = $search;
            $searchArr = explode(' ', $searchStr);
            $search = null;

            if (!empty($searchStr)) {

                foreach ($searchArr as $searchItem) {
                    $searchItem = se_db_input($searchItem);
                    if (!empty($search))
                        $search .= " AND ";
                    $price = floatval($searchItem);
                    $search .= "(`sp`.`name` like '%$searchItem%' OR `sg`.`name` like '%$searchItem%' OR `sp`.`id` = '$searchItem'
                                         OR `sp`.`code` like '$searchItem%' OR `sp`.`article` like '%$searchItem%' OR `sb`.`name` like '$searchItem%'";
                    if (!empty($price)) {
                        $search .= " OR `sp`.`price`='$price'";
                    }
                    $search .= ")";
                }
            }


            if (!empty($filter))
                $where = $filter;

            if (!empty($search)) {
                if (!empty($where))
                    $where = "(" . $where . ") AND (" . $search . ")";
                else $where = $search;
            }

            if (!empty($filterGroups)) {
                if (!empty($where))
                    $where = "(" . $where . ") AND (" . $filterGroups . ")";
                else $where = $filterGroups;
            }
            if ($filter && strpos($filter, '[idGroup]')) {

            }

            if (!empty($where))
                $sqlDelete .= " AND id_price in (SELECT sp.id FROM shop_price `sp` 
                     INNER JOIN shop_group `sg` ON sg.id = sp.id_group
                     LEFT JOIN shop_brand `sb` ON sb.id = sp.id_brand
                     WHERE {$where})";
            se_db_query($sqlDelete);


            foreach ($groups as $group) {
                $sql = "INSERT IGNORE INTO shop_price_group (id_price, id_group, is_main) 
                              SELECT sp.id, {$group->id}, 0 FROM shop_price `sp`";
                if (!empty($where)) {
                    $sql .= ' INNER JOIN shop_group `sg` ON sg.id = sp.id_group';
                    $sql .= ' LEFT JOIN shop_brand `sb` ON sb.id = sp.id_brand';
                    $sql .= " WHERE {$where}";
                }
                se_db_query($sql);
            }

        } else {

            $idsGroups = array();
            $data = null;
            foreach ($groups as $group) {
                $idsGroups[] = $group->id;
                foreach ($idsProducts as $idProduct) {
                    $u = new seTable('shop_price_group', 'spg');
                    $u->select('spg.id');
                    $u->where('spg.id_group = ?', $group->id);
                    $u->andWhere('spg.id_price = ?', $idProduct);
                    $result = $u->fetchOne();
                    if (empty($result))
                        $data[] = array('id_price' => $idProduct, 'id_group' => $group->id, 'is_main' => 0);
                }
            }

            if (!empty($data)) {
                se_db_InsertList('shop_price_group', $data);
            }

            $idsGroups = implode(",", $idsGroups);
            $u = new seTable('shop_price_group', 'spg');
            $u->where('NOT is_main AND id_price in (?)', $idsStr);
            if ($idsGroups)
                $u->andWhere('NOT id_group IN (?)', $idsGroups);
            $u->deleteList();
        }


    } else {
        $u = new seTable('shop_group_price', 'sgp');
        $u->where('price_id in (?)', $idsStr)->deletelist();

        foreach ($groups as $g)
            foreach ($idsProducts as $idProduct)
                $data[] = array('price_id' => $idProduct, 'group_id' => $g->id);

        if (!empty($data))
            se_db_InsertList('shop_group_price', $data);
    }
}

function saveDiscounts($idsProducts, $discounts)
{
    $idsStr = implode(",", $idsProducts);
    $u = new seTable('shop_discount_links', 'sdl');
    $u->where('id_price in (?)', $idsStr)->deletelist();
    foreach ($discounts as $discount)
        foreach ($idsProducts as $idProduct)
            $data[] = array('id_price' => $idProduct, 'discount_id' => $discount->id);
    if (!empty($data))
        se_db_InsertList('shop_discount_links', $data);
}

function saveFiles($idsProducts, $files)
{
    $idsStr = implode(",", $idsProducts);
    $u = new seTable('shop_files', 'sf');
    $u->where('id_price in (?)', $idsStr)->deletelist();
    foreach ($files as $file)
        foreach ($idsProducts as $idProduct)
            $data[] = array('id_price' => $idProduct, 'file' => $file->file, 'name' => $file->name);
    if (!empty($data))
        se_db_InsertList('shop_files', $data);
}

function saveLabels($idsProducts, $labels)
{
    $data = [];

    if (empty($labels)) {
        $idsProductsStr = implode(",", $idsProducts);
        $u = new seTable('shop_label_product');
        $u->where('id_product IN (?)', $idsProductsStr)->deleteList();
    }

    foreach ($labels as $label) {
        foreach ($idsProducts as $idProduct) {
            $u = new seTable('shop_label_product', 'slp');
            $u->select("slp.id");
            $u->where('slp.id_product = ?', $idProduct);
            $u->andWhere('slp.id_label = ?', $label->id);
            $result = $u->fetchOne();

            if (empty($result)) {
                if ($label->isChecked)
                    $data[] = array('id_product' => $idProduct, 'id_label' => $label->id);
            } else {
                if (!$label->isChecked) {
                    $u = new seTable('shop_label_product');
                    $u->where('id_product = ?', $idProduct);
                    $u->andWhere('id_label = ?', $label->id)->deleteList();
                }
            }
        }
    }

    if (!empty($data))
        se_db_InsertList('shop_label_product', $data);
}

function saveOptions($idsProducts, $options)
{
    if (!$_SESSION["isShowOptions"])
        return;

    $values = array();
    foreach ($options as $option) {
        foreach ($option->optionValues as $value)
            $values[] = $value;

        foreach ($idsProducts as $idProduct) {
            $u = new seTable('shop_product_option_position', 'pop');
            $u->select("pop.id");
            $u->where("pop.id_product = ?", $idProduct);
            $u->andWhere('pop.id_option  = ?', $option->id);
            $u->fetchOne();
            $u->id_product = $idProduct;
            $u->id_option = $option->id;
            $u->position = $option->position;
            $u->save();
        }

    }

    foreach ($idsProducts as $idProduct) {
        $idsExist = array();
        foreach ($values as $value) {
            $id = null;
            $isNew = true;
            $isUpdated = false;
            $u = new seTable('shop_product_option', 'spo');
            $u->select("spo.id");
            $u->where("spo.id_product = ?", $idProduct);
            $u->andWhere("spo.id_option_value = ?", $value->id);
            $result = $u->fetchOne();
            if ($result) {
                $isNew = false;
                $id = $result["id"];
            }

            $u = new seTable('shop_product_option');
            $isUpdated |= setField($isNew, $u, $idProduct, 'id_product');
            $isUpdated |= setField($isNew, $u, $value->id, 'id_option_value');
            $isUpdated |= setField($isNew, $u, (real)$value->price, 'price');
            $isUpdated |= setField($isNew, $u, (bool)$value->isDefault, 'is_default');
            $isUpdated |= setField($isNew, $u, (int)$value->sortIndex, 'sort');
            if ($isUpdated) {
                if (!empty($id))
                    $u->where('id = ?', $id);
                $u->save();
            }
            $idsExist[] = $value->id;
        }
        if ($idsExist) {
            $u = new seTable('shop_product_option');
            $u->where("id_product = ?", $idProduct);
            $u->andWhere("NOT id_option_value IN (?)", implode(',', $idsExist))->deleteList();
        }
    }
}

function saveIdGroup($idsProducts, $idGroup)
{
    $idsStr = implode(",", $idsProducts);

    $u = new seTable('shop_price_group');
    $u->where("is_main AND id_price IN (?)", $idsStr);
    if ($idGroup)
        $u->andWhere('id_group <> ?', $idGroup);
    $u->deleteList();

    if (!$idGroup)
        return;

    $data = array();
    foreach ($idsProducts as $idProduct) {
        $u = new seTable('shop_price_group', 'spg');
        $u->select('spg.id, spg.id_group, spg.is_main');
        $u->where('spg.id_price = ?', $idProduct);
        $result = $u->fetchOne();
        if (empty($result)) {
            if ($idGroup)
                $data[] = array('id_price' => $idProduct, 'id_group' => $idGroup, 'is_main' => 1);
        } else {
            if ($result["id_group"] != $idGroup) {
                $u = new seTable('shop_price_group');
                $u->addupdate('id_group', $idGroup);
                $u->where('id = ?', $result["id"]);
                $u->save();
            }
        }
    }

    if (!empty($data))
        se_db_InsertList('shop_price_group', $data);

}

function saveMeasures($idsProducts, $data)
{
    if (empty($data->idWeightView) && empty($data->idWeightEdit) &&
        empty($data->idVolumeView) && empty($data->idVolumeEdit)
    ) return;

    foreach ($idsProducts as $idProduct) {
        $id = null;
        $u = new seTable('shop_price_measure', 'spm');
        $u->select("spm.id");
        $u->where("spm.id_price = ?", $idProduct);
        $result = $u->fetchOne();
        $isNew = empty($result);
        if (!$isNew)
            $id = $result["id"];

        $u = new seTable('shop_price_measure');
        setField($isNew, $u, $idProduct, 'id_price');
        if (!empty($data->idWeightView))
            setField($isNew, $u, $data->idWeightView, 'id_weight_view');
        if (!empty($data->idWeightEdit))
            setField($isNew, $u, $data->idWeightEdit, 'id_weight_edit');
        if (!empty($data->idVolumeView))
            setField($isNew, $u, $data->idVolumeView, 'id_volume_view');
        if (!empty($data->idVolumeEdit))
            setField($isNew, $u, $data->idVolumeEdit, 'id_volume_edit');

        if (!empty($id)) {
            $u->where('id = ?', $id);
            $u->save();
        } else $u->save();
    }
}

function getBrandByName($name)
{
    $brand = new stdClass();

    $u = new seTable('shop_brand');
    $u->select("id");
    $u->where('name = "?"', $name);
    $result = $u->fetchOne();
    if (!empty($result["id"])) {
        $brand->id = $result["id"];
        return $brand;
    }

    $u->name = $name;
    $u->code = strtolower(se_translite_url($name));
    $brand->id = $u->save();
    return $brand;
}

function getCode($code)
{
    $code_n = $code;
    $u = new seTable('shop_price', 'sp');
    $i = 1;
    while ($i < 1000) {
        $u->findlist("sp.code='$code_n'")->fetchOne();
        if ($u->id)
            $code_n = $code . "-$i";
        else return $code_n;
        $i++;
    }
    return uniqid();
}

$ids = array();
if (empty($json->ids) && !empty($json->id))
    $ids[] = $json->id;
else $ids = $json->ids;
$isNew = empty($ids);
if (!$isNew)
    $idsStr = implode(",", $ids);

$u = new seTableEx('shop_price', 'sp');

if ($isNew || !empty($ids)) {
    $isUpdated = false;
    if (isset($json->isActive)) {
        $json->isActive = $json->isActive ? 'Y' : 'N';
        $isUpdated |= setField($isNew, $u, $json->isActive, 'enabled');
    }
    $isUpdated |= setField($isNew, $u, $json->name, 'name');
    if ($isNew || isset($json->code)) {
        if (empty($json->code))
            $json->code = strtolower(se_translite_url($json->name));
        else $json->code = strtolower(se_translite_url($json->code));
        $json->code = getCode($json->code);
        $isUpdated |= setField($isNew, $u, $json->code, 'code');
    }

    if (isset($json->article) && empty($json->article))
        $json->article = maxArticle($json->idGroup);

    if (!empty($json->article) && !$json->isCloneMode)
        checkArticle($ids[0], $json->article);
    $isUpdated |= setField($isNew, $u, $json->article, 'article');

    if ($isNew) {
        if (empty($json->isInfinitely) && $json->count <= 0)
            $json->count = 0.0;
    }

    $isUpdated |= setField($isNew, $u, $json->idGroup, 'id_group');
    $isUpdated |= setField($isNew, $u, $json->idType, 'id_type');
    $isUpdated |= setField($isNew, $u, $json->price, 'price');
    $isUpdated |= setField($isNew, $u, $json->pricePurchase, 'price_purchase', "DECIMAL(10, 2) UNSIGNED DEFAULT NULL COMMENT 'Закупочная цена товара' AFTER price");
    $isUpdated |= setField($isNew, $u, $json->priceMiniWholesale, 'price_opt');
    $isUpdated |= setField($isNew, $u, $json->priceWholesale, 'price_opt_corp');
    $isUpdated |= setField($isNew, $u, $json->measurement, 'measure');
    $isUpdated |= setField($isNew, $u, $json->currency, 'curr');
    $isUpdated |= setField($isNew, $u, $json->bonus, 'bonus');
    $isUpdated |= setField($isNew, $u, $json->rate, 'rate');
    $isUpdated |= setField($isNew, $u, $json->tax, 'nds');
    $isUpdated |= setField($isNew, $u, $json->sortIndex, 'sort');

    if (isset($json->isInfinitely) && $json->isInfinitely && !isset($json->count))
        $json->count = -1;
    if (isset($json->count) && empty($json->count))
        $json->count = 0;

    if (isset($json->images)) {
        foreach ($json->images as $image) {
            if ($image->isMain) {
                $json->imageFile = $image->imageFile;
                $json->imageAlt = $image->imageAlt;
                break;
            }
        }
    }

    $isUpdated |= setField($isNew, $u, $json->imageFile, 'img');
    $isUpdated |= setField($isNew, $u, $json->imageAlt, 'img_alt');
    $isUpdated |= setField($isNew, $u, $json->count, 'presence_count');
    $isUpdated |= setField($isNew, $u, $json->stepCount, 'step_count');
    $isUpdated |= setField($isNew, $u, $json->minCount, 'min_count');
    $isUpdated |= setField($isNew, $u, $json->precense, 'presence');

    if (isset($json->isYAMarket) && !$json->isYAMarket)
        $json->isYAMarket = "0";
    if (isset($json->isYAMarketAvailable) && !$json->isYAMarketAvailable)
        $json->isYAMarketAvailable = "0";
    if (isset($json->isShowFeatures) && !$json->isShowFeatures)
        $json->isShowFeatures = "0";
    $isUpdated |= setField($isNew, $u, $json->isYAMarket, 'is_market');
    $isUpdated |= setField($isNew, $u, $json->isYAMarketAvailable, 'market_available');
    $isUpdated |= setField($isNew, $u, $json->isShowFeatures, 'is_show_feature');

    if (isset($json->isNew)) {
        $isUpdated = true;
        if ($json->isNew) {
            if ($isNew)
                $u->flag_new = 'Y';
            else $u->addupdate('flag_new', "'Y'");
        } else {
            if ($isNew)
                $u->flag_new = 'N';
            else $u->addupdate('flag_new', "'N'");
        }
    }
    if (isset($json->isHit)) {
        $isUpdated = true;
        if ($json->isHit) {
            if ($isNew)
                $u->flag_hit = 'Y';
            else $u->addupdate('flag_hit', "'Y'");
        } else {
            if ($isNew)
                $u->flag_hit = 'N';
            else $u->addupdate('flag_hit', "'N'");
        }
    }
    if (isset($json->isSpecial)) {
        $isUpdated = true;
        if ($json->isSpecial) {
            if ($isNew)
                $u->special_offer = 'Y';
            else $u->addupdate('special_offer', "'Y'");
        } else {
            if ($isNew)
                $u->special_offer = 'N';
            else $u->addupdate('special_offer', "'N'");
        }
    }
    if (isset($json->isAction)) {
        $isUpdated = true;
        if ($json->isAction) {
            if ($isNew)
                $u->unsold = 'Y';
            else $u->addupdate('unsold', "'Y'");
        } else {
            if ($isNew)
                $u->unsold = 'N';
            else $u->addupdate('unsold', "'N'");
        }
    }
    $isUpdated |= setField($isNew, $u, $json->manufacturer, 'manufacturer');
    $isUpdated |= setField($isNew, $u, $json->idMmanufacturer, 'id_manufacturer');
    $isUpdated |= setField($isNew, $u, $json->dateManufactured, 'date_manufactured');
    if (!empty($json->brandName) && !isset($json->brand))
        $json->brand = getIdBrandByName($json->brandName);

    if (isset($json->brand)) {
        if ($json->brand->id)
            $isUpdated |= setField($isNew, $u, $json->brand->id, 'id_brand');
        else {
            if (!$isNew)
                $isUpdated |= setField($isNew, $u, $json->brand, 'id_brand');
        }
    }
    $isUpdated |= setField($isNew, $u, $json->description, 'note');
    $isUpdated |= setField($isNew, $u, $json->volume, 'volume');
    $isUpdated |= setField($isNew, $u, $json->weight, 'weight');
    if (isset($json->isDiscount)) {
        $isUpdated = true;
        if ($json->isDiscount) {
            if ($isNew)
                $u->discount = 'Y';
            else $u->addupdate('discount', "'Y'");
        } else {
            if ($isNew)
                $u->discount = 'N';
            else $u->addupdate('discount', "'N'");
        }
    }
    $isUpdated |= setField($isNew, $u, $json->maxDiscount, 'max_discount');
    if ($json->method != "addDescription")
        $isUpdated |= setField($isNew, $u, $json->fullDescription, 'text');
    $isUpdated |= setField($isNew, $u, $json->seoHeader, 'title');
    $isUpdated |= setField($isNew, $u, $json->seoKeywords, 'keywords');
    $isUpdated |= setField($isNew, $u, $json->seoDescription, 'description');
    $isUpdated |= setField($isNew, $u, $json->breadCrumb, 'bread_crumb');
    $isUpdated |= setField($isNew, $u, $json->idYAMarketCategory, 'market_category');

    if ($isUpdated) {
        if (!empty($idsStr)) {
            if (strpos($idsStr, "*") === false) {
                $u->where('id IN (?)', $idsStr);
                $u->save();
            } else {
                if (!empty($json->filter)) {
                    $filter = convertFields($json->filter);
                    $fields = $u->getUpdate();
                    $tableName = $u->getTableName();
                    $sql = "UPDATE shop_price ";
                    if (CORE_VERSION != "5.2") {
                        $sql .= " LEFT JOIN shop_price_group spg ON spg.id_price = {$tableName}.id";
                        $sql .= " LEFT JOIN shop_group sg ON spg.id_price = sg.id = spg.id_group";
                    } else {
                        $sql .= " LEFT JOIN shop_group sg ON sg.id = {$tableName}.id_group";
                    }
                    $sql .= " LEFT JOIN shop_crossgroup scg ON scg.group_id = {$tableName}.id_group";
                    $sql .= " LEFT JOIN shop_group_price sgp ON {$tableName}.id = sgp.price_id";
                    $sql .= " SET ";
                    foreach ($fields as $field => $value)
                        $sql .= $tableName . "." . $field . '=' . $value . ',';
                    $sql .= $tableName . ".updated_at = '" . date("Y-m-d H:i:s", time()) . "'";
                    $sql .= " WHERE {$filter}";
                    se_db_query_ex($sql);
                } else {
                    $u->where('TRUE');
                    $u->save();
                }
            }
        } else $ids[] = $u->save();
    }

    if ($ids && isset($json->images))
        saveImages($ids, $json->images);
    if ($ids && isset($json->specifications))
        saveSpecifications($ids, $json->specifications, $json->isAddSpecifications);
    if ($ids && isset($json->modifications))
        saveModifications($ids, $json->modifications);
    if ($ids && isset($json->similarProducts))
        saveSimilarProducts($ids, $json->similarProducts);
    if ($ids && isset($json->accompanyingProducts))
        saveAccompanyingProducts($ids, $json->accompanyingProducts);
    if ($ids && isset($json->comments))
        saveComments($ids, $json->comments);
    if ($ids && isset($json->crossGroups))
        saveCrossGroups($ids, $json->crossGroups, $json->filter, $json->search);
    if ($ids && isset($json->discounts))
        saveDiscounts($ids, $json->discounts);
    if ($ids && isset($json->files))
        saveFiles($ids, $json->files);
    if ($ids && isset($json->labels))
        saveLabels($ids, $json->labels);
    if ($ids && isset($json->options))
        saveOptions($ids, $json->options);

    if ($ids && isset($json->idGroup))
        saveIdGroup($ids, $json->idGroup);

    if ($ids)
        saveMeasures($ids, $json);

    if ($json->method == "addDescription" && !empty($json->fullDescription)) {

        $textAddList = array();
        preg_match_all('#<tab title="(.+)">(.+)</tab>#U', $json->fullDescription, $matches);
        if (!empty($matches[1])) {
            $i = 0;
            foreach ($matches[1] as $t)
                $textAddList[$t] = $matches[2][$i++];
        } else $textAddList["Описание товара"] = $json->fullDescription;

        $t = new seTable("shop_price", "sp");
        $t->select("sp.id, sp.text");
        $t->where("sp.id IN (?)", $idsStr);
        $items = $t->getList();
        foreach ($items as $item) {
            $text = $item["text"];
            $textList = array();
            preg_match_all('#<tab title="(.+)">(.+)</tab>#U', $text, $matches);
            if (empty($matches[1]))
                $textList["Описание товара"] = $text;
            else {
                $i = 0;
                foreach ($matches[1] as $t)
                    $textList[$t] = $matches[2][$i++];
            }
            $newText = null;
            foreach ($textList as $key => $text) {
                if (!empty($textAddList[$key]))
                    $text .= "\n<br>" . $textAddList[$key];
                $text = '<tab title="' . $key . '">' . $text . '</tab>';
                $newText .= $text;
            }
            foreach ($textAddList as $key => $text)
                if (!key_exists($key, $textList))
                    $newText .= '<tab title="' . $key . '">' . $text . '</tab>';

            if ($newText) {
                $t = new seTable("shop_price", "sp");
                setField(false, $t, $newText, 'text');
                $t->where("id = ?", $item["id"]);
                $t->save();
            }
        }
    }
}

$data['id'] = $ids[0];
$status = array();
if (!se_db_error()) {
    $status['status'] = 'ok';
    $status['data'] = $data;
    file_get_contents('http://' . $json->hostname . "/lib/shoppreorder_checkCount.php?id={$data["id"]}");
} else {
    $status['status'] = 'error';
    $status['error'] = 'Не удаётся сохранить данные о товаре!';
}

outputData($status);