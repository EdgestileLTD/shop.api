<?php

function getCode($code)
{
    $code_n = $code;
    $u = new seTable('shop_group', 'sg');
    $i = 1;
    while ($i < 1000) {
        $u->findlist("sg.code_gr='$code_n'")->fetchOne();
        if ($u->id)
            $code_n = $code . "-$i";
        else return $code_n;
        $i++;
    }
    return uniqid();
}

function saveDiscounts($idsGroups, $discounts)
{
    $idsStr = implode(",", $idsGroups);
    $u = new seTable('shop_discount_links', 'sdl');
    $u->where('id_group in (?)', $idsStr)->deletelist();
    foreach ($discounts as $discount)
        foreach ($idsGroups as $idGroup)
            $data[] = array('id_group' => $idGroup, 'discount_id' => $discount->id);
    if (!empty($data))
        se_db_InsertList('shop_discount_links', $data);
}

function setSortPosition($id, $idParent)
{
    $u = new seTable('shop_group', 'sg');
    $u->select('MAX(position) as maxPos');
    if ($idParent)
        $u->where('upid=?', $idParent);
    else $u->where('upid IS NULL OR upid = 0');
    $u->fetchOne();
    $sortPos = $u->maxPos + 1;
    $s = new seTable('shop_group', 'sg');
    $s->select("id, position");
    $s->find($id);
    if ($s->id) {
        $s->position = $sortPos;
        $s->save();
    }
}

function saveLinksGroups($idsGroups, $links)
{
    $idsExists = array();
    foreach ($links as $product)
        if ($product->id)
            $idsExists[] = $product->id;
    if (CORE_VERSION != "5.3")
        $idsExists = array_diff($idsExists, $idsGroups);
    $idsExistsStr = implode(",", $idsExists);
    $idsStr = implode(",", $idsGroups);

    if (CORE_VERSION == "5.3") {
        $u = new seTable('shop_price_group', 'spg');
        if ($idsExistsStr)
            $u->where("(NOT id_price IN ({$idsExistsStr})) AND id_group IN (?)", $idsStr)->deleteList();
        else $u->where('id_group IN (?)', $idsStr)->deleteList();
        $idsExists = array();
        if ($idsExistsStr) {
            $u->select("id_price, id_group");
            $u->where("(id_price IN ({$idsExistsStr})) AND id_group IN (?)", $idsStr);
            $objects = $u->getList();
            foreach ($objects as $item)
                $idsExists[] = $item["id_price"];
        };
        $data = array();
        foreach ($links as $product)
            if (empty($idsExists) || !in_array($product->id, $idsExists))
                foreach ($idsGroups as $idGroup)
                    $data[] = array('id_price' => $product->id, 'id_group' => $idGroup, 'is_main' => 0);
        if (!empty($data))
            se_db_InsertList('shop_price_group', $data);
    } else {
        $u = new seTable('shop_crossgroup', 'scg');
        if ($idsExistsStr)
            $u->where("(NOT group_id IN ({$idsExistsStr})) AND id IN (?)", $idsStr)->deleteList();
        else $u->where('id IN (?)', $idsStr)->deleteList();
        $idsExists = array();
        if ($idsExistsStr) {
            $u->select("id, group_id");
            $u->where("(group_id IN ({$idsExistsStr})) AND id IN (?)", $idsStr);
            $objects = $u->getList();
            foreach ($objects as $item) {
                $idsExists[] = $item["id"];
                $idsExists[] = $item["group_id"];
            }
        };
        $data = array();
        foreach ($links as $product)
            if (empty($idsExists) || !in_array($product->id, $idsExists))
                foreach ($idsGroups as $idGroup)
                    $data[] = array('id' => $idGroup, 'group_id' => $product->id);
        if (!empty($data))
            se_db_InsertList('shop_crossgroup', $data);
    }
}

function saveParametersFilters($idsGroups, $filters)
{
    $idsStr = implode(",", $idsGroups);
    $u = new seTable('shop_group_filter', 'sgf');
    $u->where('id_group in (?)', $idsStr)->deletelist();

    foreach ($filters as $filter)
        foreach ($idsGroups as $idGroup)
            if ($filter->id)
                $data[] = array('id_group' => $idGroup, 'id_feature' => $filter->id,
                    'expanded' => $filter->isActive, 'sort' => $filter->sortIndex);
    if (!empty($data))
        se_db_InsertList('shop_group_filter', $data);

    $data = null;
    foreach ($filters as $filter)
        foreach ($idsGroups as $idGroup)
            if (!empty($filter->code))
                $data[] = array('id_group' => $idGroup, 'default_filter' => $filter->code,
                    'expanded' => $filter->isActive, 'sort' => $filter->sortIndex);
    if (!empty($data))
        se_db_InsertList('shop_group_filter', $data);
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
            $u = new seTable('shop_group_img', 'sgi');
            $isUpdated = false;
            $isUpdated |= setField(false, $u, $image->imageFile, 'picture');
            $isUpdated |= setField(false, $u, $image->sortIndex, 'sort');
            $isUpdated |= setField(false, $u, $image->imageAlt, 'picture_alt');
            $u->where('id=?', $image->id);
            if ($isUpdated)
                $u->save();
        }
    }

    $idsStr = implode(",", $idsProduct);
    if (!empty($idsStore)) {
        $u = new seTable('shop_group_img', 'sgi');
        $u->where("id_group in ($idsStr) AND NOT (id in (?))", $idsStore)->deletelist();
    } else {
        $u = new seTable('shop_group_img', 'sgi');
        $u->where('id_group in (?)', $idsStr)->deletelist();
    }

    $data = array();
    foreach ($images as $image)
        if (empty($image->id) || ($image->id <= 0)) {
            foreach ($idsProduct as $idProduct) {
                $data[] = array('id_group' => $idProduct, 'picture' => $image->imageFile,
                    'sort' => $image->sortIndex, 'picture_alt' => $image->imageAlt);
                $newImages[] = $image->imageFile;
            }
        }

    if (!empty($data))
        se_db_InsertList('shop_group_img', $data);
}

function getLevel($id)
{
    global $DBH;

    $level = 0;
    $sqlLevel = 'SELECT `level` FROM shop_group_tree WHERE id_parent = :id_parent AND id_child = :id_parent LIMIT 1';
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

    $DBH->query("DELETE FROM shop_group_tree WHERE id_child = {$id}");

    $level = 0;
    $sqlGroupTree = "INSERT INTO shop_group_tree (id_parent, id_child, `level`)
                            SELECT id_parent, :id, :level FROM shop_group_tree
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

$ids = array();
if (empty($json->ids) && !empty($json->id))
    $ids[] = $json->id;
else $ids = $json->ids;
$isNew = empty($ids);
if (!$isNew)
    $idsStr = implode(",", $ids);

$u = new seTable('shop_group', 'sg');

if ($isNew || !empty($ids)) {
    $isUpdated = false;
    $isUpdated |= setField($isNew, $u, $json->code, 'code_gr');
    if ($isNew) {
        if (!$u->code_gr)
            $u->code_gr = strtolower(se_translite_url($json->name));
        $u->code_gr = getCode($u->code_gr);
    }
    if (isset($json->name))
        $json->name = str_replace("/", "_", $json->name);
    $isUpdated |= setField($isNew, $u, $json->name, 'name');

    if (CORE_VERSION != "5.3" && isset($json->idParent)) {
        if ($json->idParent && $json->idParent != $json->id)
            $isUpdated |= setField($isNew, $u, $json->idParent, 'upid');
        else $isUpdated |= setField($isNew, $u, "", 'upid');
    }

    $isUpdated |= setField($isNew, $u, $json->description, 'commentary');
    $isUpdated |= setField($isNew, $u, $json->fullDescription, 'footertext');
    $isUpdated |= setField($isNew, $u, $json->imageAlt, 'picture_alt');
    $isUpdated |= setField($isNew, $u, $json->imageFile, 'picture');
    $isUpdated |= setField($isNew, $u, $json->seoHeader, 'title');
    $isUpdated |= setField($isNew, $u, $json->seoKeywords, 'keywords');
    $isUpdated |= setField($isNew, $u, $json->seoDescription, 'description');
    $isUpdated |= setField($isNew, $u, $json->sortIndex, 'position');
    $isUpdated |= setField($isNew, $u, $json->idModificationGroupDef, 'id_modification_group_def');
    if (isset($json->isActive)) {
        if ($json->isActive)
            $isUpdated |= setField($isNew, $u, 'Y', 'active');
        else $isUpdated |= setField($isNew, $u, 'N', 'active');
    }

    if ($isUpdated) {
        if (!empty($idsStr)) {
            if ($idsStr != "all")
                $u->where('id in (?)', $idsStr);
            else $u->where('true');
        }
        $idv = $u->save();
        if ($isNew)
            $ids[] = $idv;
    }
    if ($ids && isset($json->discounts))
        saveDiscounts($ids, $json->discounts);
    if ($ids && isset($json->parametersFilters))
        saveParametersFilters($ids, $json->parametersFilters);
    if ($ids && isset($json->images))
        saveImages($ids, $json->images);
    if ($isNew && $ids[0])
        setSortPosition($ids[0], $json->idParent);
    if ($ids && isset($json->linksGroups))
        saveLinksGroups($ids, $json->linksGroups);

    if (CORE_VERSION == "5.3") {
        if ($isNew || isset($json->idParent))
            saveIdParent($ids[0], $json->idParent);
    }

}

$data['id'] = $ids[0];
$status = array();
if (!se_db_error()) {
    $status['status'] = 'ok';
    $status['data'] = $data;
} else {
    $status['status'] = 'error';
    $status['errortext'] = 'Не удаётся сохранить категорию для товаров!';
}

outputData($status);