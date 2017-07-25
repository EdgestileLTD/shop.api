<?php

$id = $_GET['id'];
if (!$id) {

    if (empty($json->ids))
        exit;

    if (sizeof($json->ids))
        $id = $json->ids[0];
    else exit;
}

if ($id == "all") {
    $status['status'] = 'ok';
    $status['data'] = array("count", "items");
    outputData($status);
    exit;
}


function getDiscounts($id)
{
    $answer = array();
    $u = new seTable('shop_discounts', 'sd');
    $u->select('sd.*');
    $u->innerjoin('shop_discount_links sdl', 'sdl.discount_id = sd.id');
    $u->where('sdl.id_price=?', $id);
    $u->orderby('sd.id');
    $items = $u->getList();
    foreach ($items as $item) {
        $discount = null;
        $discount['id'] = $item['id'];
        $discount['name'] = $item['title'];
        $discount['stepTime'] = (int)$item['step_time'];
        $discount['stepDiscount'] = (float)$item['step_discount'];
        $discount['dateTimeFrom'] = $item['date_from'];
        $discount['dateTimeTo'] = $item['date_to'];
        $discount['week'] = $item['week'];
        $discount['sumFrom'] = (float)$item['summ_from'];
        $discount['sumTo'] = (float)$item['summ_to'];
        $discount['countFrom'] = (float)$item['count_from'];
        $discount['countTo'] = (float)$item['count_to'];
        $discount['discount'] = (float)$item['discount'];
        $discount['typeDiscount'] = $item['type_discount'];
        $discount['typeSum'] = $item['summ_type'];
        $answer[] = $discount;
    }
    return $answer;
}


function getImages($id, &$product)
{
    global $json;

    $lang = FOLDER_SHOP ? FOLDER_SHOP : "rus";

    $u = new seTable('shop_img', 'si');
    $u->where('si.id_price=?', $id);
    $u->orderby("sort");
    $objects = $u->getList();

    foreach ($objects as $item) {
        $image = null;
        $image['id'] = $item['id'];
        $image['imageFile'] = $item['picture'];
        $image['imageAlt'] = $item['picture_alt'];
        $image['sortIndex'] = $item['sort'];
        $image['isMain'] = (bool)$item['default'];
        if ($image['imageFile']) {
            if (strpos($image['imageFile'], "://") === false) {
                $image['imageUrl'] = 'http://' . $json->hostname . "/images/{$lang}/shopprice/" . $image['imageFile'];
                $image['imageUrlPreview'] = "http://{$json->hostname}/lib/image.php?size=64&img=images/{$lang}/shopprice/" . $image['imageFile'];
            } else {
                $image['imageUrl'] = $image['imageFile'];
                $image['imageUrlPreview'] = $image['imageFile'];
            }
        }
        if (empty($product["imageFile"]) && $image['isMain']) {
            $product["imageFile"] = $image['imageFile'];
            $product["imageAlt"] = $image['imageAlt'];
        }
        $product['images'][] = $image;
    }
}

function lessModification($mod1, $mod2)
{
    for ($i = 0; $i < sizeof($mod1["sortValue"]); $i++) {
        if ($mod1["sortValue"][$i] < $mod2["sortValue"][$i])
            return true;
        if ($mod1["sortValue"][$i] > $mod2["sortValue"][$i])
            return false;
    }
    return false;
}

function sortModifications($items)
{
    $length = sizeof($items);
    $result = $items;
    for ($j = 0; $j < $length - 1; $j++) {
        for ($i = 0; $i < $length - 1; $i++) {
            if (lessModification($result[$i + 1], $result[$i])) {
                $b = $result[$i];
                $result[$i] = $result[$i + 1];
                $result[$i + 1] = $b;
            }
        }
    }
    return $result;
}

function sortColumns($items)
{
    $length = sizeof($items);
    $result = $items;
    for ($j = 0; $j < $length - 1; $j++) {
        for ($i = 0; $i < $length - 1; $i++) {
            if ($result[$i]['sortIndex'] > $result[$i + 1]['sortIndex']) {
                $b = $result[$i];
                $result[$i] = $result[$i + 1];
                $result[$i + 1] = $b;
            }
        }
    }
    return $result;
}

function getModifications($ids, &$product)
{
    global $json;

    $lang = FOLDER_SHOP ? FOLDER_SHOP : "rus";
    $idsStr = implode(",", $ids);

    $newTypes = array("string" => "S", "number" => "D", "bool" => "B", "list" => "L", "colorlist" => "CL");

    $u = new seTable('shop_modifications', 'sm');
    $u->select('smg.*,
                GROUP_CONCAT(DISTINCT(CONCAT_WS("\t", sf.id, sf.name, sf.`type`, sf.sort)) SEPARATOR "\n") AS `columns`');
    $u->innerjoin('shop_modifications_group smg', 'smg.id = sm.id_mod_group');
    $u->innerjoin('shop_modifications_feature smf', 'smf.id_modification = sm.id');
    $u->innerjoin('shop_feature sf', 'sf.id = smf.id_feature');
    $u->where('sm.id_price IN (?)', $idsStr);
    $u->groupby('smg.id');
    $u->orderby('smg.sort');
    $objects = $u->getList();
    foreach ($objects as $item) {
        $group = null;
        $group['id'] = $item['id'];
        $group['name'] = $item['name'];
        $group['sortIndex'] = $item['sort'];
        $group['type'] = $item['vtype'];
        if (!$product["idGroupModification"]) {
            $product["idGroupModification"] = $group['id'];
            $product["nameGroupModification"] = $group['name'];
        }
        $items = explode("\n", $item['columns']);
        foreach ($items as $item) {
            $item = explode("\t", $item);
            $column['id'] = $item[0];
            $column['name'] = $item[1];
            $column['type'] = $item[2];
            $column['sortIndex'] = $item[3];
            $column['valueType'] = $newTypes[$column['type']];
            $group['columns'][] = $column;
        }
        $group['items'] = array();
        $groups[] = $group;
    }
    if (!isset($groups))
        return;

    $u = new seTable('shop_modifications', 'sm');
    $u->select('sm.*,
                SUBSTRING(GROUP_CONCAT(DISTINCT(CONCAT_WS("\t", sfvl.id_feature, sfvl.id, sfvl.value, sfvl.sort, sfvl.color)) SEPARATOR "\n"), 1) AS values_feature,
                SUBSTRING(GROUP_CONCAT(DISTINCT(CONCAT_WS("\t", smi.id_img, smi.sort, si.picture)) SEPARATOR "\n"), 1) AS images');
    $u->innerjoin('shop_modifications_feature smf', 'sm.id = smf.id_modification');
    $u->innerjoin('shop_feature_value_list sfvl', 'sfvl.id = smf.id_value');
    $u->leftjoin('shop_modifications_img smi', 'sm.id = smi.id_modification');
    $u->leftjoin('shop_img si', 'smi.id_img = si.id');
    $u->where('sm.id_price IN (?)', $idsStr);
    $u->groupby();
    $objects = $u->getList();
    $existFeatures = array();
    foreach ($objects as $item) {
        if ($item['id']) {
            $modification = null;
            $modification['id'] = $item['id'];
            $modification['article'] = $item['code'];
            if ($item['count'] != null)
                $modification['count'] = (real)$item['count'];
            else $modification['count'] = -1;
            if (!$modification['article'])
                $modification['article'] = $product["article"];
            if (!$modification['measurement'])
                $modification['measurement'] = $product['measurement'];
            $modification['price'] = (real)$item['value'];
            $modification['priceSmallOpt'] = (real)$item['value_opt'];
            $modification['priceOpt'] = (real)$item['value_opt_corp'];
            $modification['pricePurchase'] = (real)$item['value_purchase'];
            $modification['description'] = $item['description'];
            if (in_array($item['values_feature'], $existFeatures) && sizeof($ids) > 1)
                continue;
            $features = explode("\n", $item['values_feature']);
            $sorts = array();
            foreach ($features as $feature) {
                $feature = explode("\t", $feature);
                $value = null;
                $value['idFeature'] = $feature[0];
                $value['id'] = $feature[1];
                $value['name'] = $feature[2];
                $sorts[] = $feature[3];
                $value['color'] = $feature[4];
                $modification['values'][] = $value;
            }
            $modification['sortValue'] = $sorts;
            if ($item['images']) {
                $images = explode("\n", $item['images']);
                foreach ($images as $image) {
                    $feature = explode("\t", $image);
                    $value = null;
                    $value['id'] = $feature[0];
                    $value['sortIndex'] = $feature[1];
                    $value['imageFile'] = $feature[2];
                    if ($value['imageFile']) {
                        if (strpos($value['imageFile'], "://") === false) {
                            $value['imageUrl'] = 'http://' . $json->hostname . "/images/{$lang}/shopprice/" . $value['imageFile'];
                            $value['imageUrlPreview'] = "http://{$json->hostname}/lib/image.php?size=64&img=images/{$lang}/shopprice/" . $value['imageFile'];
                        } else {
                            $value['imageUrl'] = $image['imageFile'];
                            $value['imageUrlPreview'] = $image['imageFile'];
                        }
                    }
                    $modification['images'][] = $value;
                }
            }
            foreach ($groups as &$group) {
                if ($group['id'] == $item['id_mod_group']) {
                    $group['items'][] = $modification;
                }
            }
            $existFeatures[] = $item['values_feature'];
        }
    }
    /*
    foreach ($groups as &$group) {
        $group['columns'] = sortColumns($group['columns']);
        $group['items'] = sortModifications($group['items']);
    }
    */
    $product['modifications'] = $groups;
    $product['countModifications'] = count($objects);
}

function getSpecifications($id, &$product)
{
    $u = new seTable('shop_modifications_feature', 'smf');
    $u->select('IFNULL(sfg.id, 0) AS id_group, sfg.name AS group_name, sf.name AS name_feature,
                            sf.type, sf.measure, smf.*, sfvl.value, sfvl.color, sfg.sort AS index_group');
    $u->innerjoin('shop_feature sf', 'sf.id = smf.id_feature');
    $u->leftjoin('shop_feature_value_list sfvl', 'smf.id_value = sfvl.id');
    $u->leftjoin('shop_feature_group sfg', 'sfg.id = sf.id_feature_group');
    $u->where('smf.id_price=? AND smf.id_modification IS NULL', $id);
    $u->orderby('sfg.sort');
    $u->addorderby('sf.sort');

    $newTypes = array("string" => "S", "number" => "D", "bool" => "B", "list" => "L", "colorlist" => "CL");

    $objects = $u->getList();
    foreach ($objects as $item) {
        $specification = null;
        $specification['id'] = $item['id'];
        $specification['name'] = $item['name_feature'];
        $specification['idGroup'] = $item['id_group'];
        $specification['nameGroup'] = $item['group_name'];
        $specification['idFeature'] = $item['id_feature'];
        $specification['type'] = $item['type'];
        $specification['valueType'] = $newTypes[$item['type']];
        $specification['measure'] = $item['measure'];
        $specification['valueIdList'] = $item['id_value'];
        $specification['idValue'] = $item['id_value'];
        $specification['valueList'] = $item['value'];
        $specification['valueNumber'] = (float)$item['value_number'];
        $specification['valueBool'] = (bool)$item['value_bool'];
        $specification['valueString'] = $item['value_string'];
        switch ($specification['valueType']) {
            case "S":
                $specification["value"] = $item['value_string'];
                break;
            case "D":
                $specification["value"] = $item['value_number'];
                break;
            case "B":
                $specification["value"] = $item['value_bool'];
                break;
            case "L":
                $specification["value"] = $item['value'];
                break;
            case "CL":
                $specification["value"] = $item['value'];
                break;
        }
        $specification['sortIndex'] = $item['sort'];
        $specification['color'] = $item['color'];
        $specification['sortIndexGroup'] = $item['index_group'];
        $product['specifications'][] = $specification;
    }
}

function getSimilarProducts($id, &$product)
{
    $u = new seTable('shop_sameprice', 'ss');
    $u->select('sp1.id as id1, sp1.name as name1, sp1.code as code1, sp1.article as article1, sp1.price as price1,
                            sp2.id as id2, sp2.name as name2, sp2.code as code2, sp2.article as article2, sp2.price as price2');
    $u->innerjoin('shop_price sp1', 'sp1.id = ss.id_price');
    $u->innerjoin('shop_price sp2', 'sp2.id = ss.id_acc');
    $u->where('sp1.id=? or sp2.id=?', $id);
    $objects = $u->getList();
    foreach ($objects as $item) {
        $similar = null;
        $i = 1;
        if ($item['id1'] == $id)
            $i = 2;
        $similar['id'] = $item['id' . $i];
        $similar['name'] = $item['name' . $i];
        $similar['code'] = $item['code' . $i];
        $similar['article'] = $item['article' . $i];
        $similar['price'] = (real)$item['price' . $i];
        $product['similarProducts'][] = $similar;
    }
}

function getAccompanyingProducts($id, &$product)
{
    $u = new seTable('shop_accomp', 'sa');
    $u->select('sp.id, sp.name, sp.code, sp.article, sp.price');
    $u->innerjoin('shop_price sp', 'sp.id = sa.id_acc');
    $u->where('sa.id_price=?', $id);
    $objects = $u->getList();
    foreach ($objects as $item) {
        $accompanying = null;
        $accompanying['id'] = $item['id'];
        $accompanying['name'] = $item['name'];
        $accompanying['code'] = $item['code'];
        $accompanying['article'] = $item['article'];
        $accompanying['price'] = (real)$item['price'];
        $product['accompanyingProducts'][] = $accompanying;
    }
}

function getComments($id, &$product)
{
    $u = new seTable('shop_comm', 'sc');
    $u->select('sc.*');
    $u->where('sc.id_price=?', $id);
    $objects = $u->getList();
    foreach ($objects as $item) {
        $comm = null;
        $comm['id'] = $item['id'];
        $comm['date'] = date('d.m.Y', strtotime($item['date']));
        $comm['idProduct'] = $id;
        $comm['contactTitle'] = $item['name'];
        $comm['contactEmail'] = $item['email'];
        $comm['commentary'] = $item['commentary'];
        $comm['response'] = $item['response'];
        $comm['isShowing'] = $item['showing'] == 'Y';
        $comm['isActive'] = $item['is_active'] == "Y";
        $product['comments'][] = $comm;
    }
}

function getCrossGroups($id, &$product)
{
    if (CORE_VERSION == "5.3") {
        $u = new seTable('shop_price_group', 'spg');
        $u->select('sg.id, sg.name');
        $u->innerjoin('shop_group sg', 'sg.id = spg.id_group');
        $u->where('spg.id_price = ? AND NOT spg.is_main', $id);
    } else {
        $u = new seTable('shop_group_price', 'sgp');
        $u->select('sg.id, sg.name');
        $u->innerjoin('shop_group sg', 'sg.id = sgp.group_id');
        $u->where('sgp.price_id=?', $id);
    }
    $objects = $u->getList();
    $product['crossGroups'] = $objects;
}

function getFiles($id, &$product)
{
    $u = new seTable('shop_files', 'sf');
    $u->select('sf.id, sf.file, sf.name');
    $u->where('sf.id_price = ?', $id);

    $objects = $u->getList();
    $product['files'] = $objects;
}

function getOptions($id, &$product)
{
    if (!$_SESSION["isShowOptions"])
        return null;

    $options = array();

    $u = new seTable('shop_product_option', 'spo');
    $u->select('so.id id_option, so.name `option`, so.type, so.type_price, 
                sov.id, sov.name, spo.price, spo.is_default, so.is_counted');
    $u->innerJoin('shop_option_value sov', 'spo.id_option_value = sov.id');
    $u->innerJoin('shop_option so', 'sov.id_option = so.id');
    $u->where('spo.id_product = ?', $id);
    $u->orderBy('so.sort');
    $u->addOrderBy('sov.sort');
    $u->groupBy("spo.id");

    $objects = $u->getList();
    $listOptions = array();
    foreach ($objects as $object) {
        $value = null;
        $value["id"] = $object["id"];
        $value["name"] = $object["name"];
        $value["price"] = (float)$object["price"];
        $value["isDefault"] = (bool)$object["is_default"];

        $listOptions[$object["id_option"]]["id"] = $object["id_option"];
        $listOptions[$object["id_option"]]["name"] = $object["option"];
        $listOptions[$object["id_option"]]["isCounted"] = $object["is_counted"];
        $listOptions[$object["id_option"]]["type"] = (int)$object["type"];
        $listOptions[$object["id_option"]]["typePrice"] = (int)$object["type_price"];
        $listOptions[$object["id_option"]]["optionValues"][] = $value;
    }

    foreach ($listOptions as $option)
        $options[] = $option;

    $product['countOptions'] = count($options);
    $product['options'] = $options;
}

$u = new seTable('shop_price', 'sp');
$u->select('sp.*, sg.id idGroup, sb.name AS nameBrand, sg.name AS nameGroup, sg.id_modification_group_def,
    spm.id_weight_view, spm.id_weight_edit, spm.id_volume_view, spm.id_volume_edit');
$u->leftjoin('shop_brand sb', 'sb.id = sp.id_brand');
if (CORE_VERSION == "5.3") {
    $u->leftjoin("shop_price_group spg", "spg.id_price = sp.id AND spg.is_main");
    $u->leftjoin('shop_group sg', 'sg.id = spg.id_group');
} else {
    $u->leftjoin('shop_group sg', 'sp.id_group = sg.id');
}
$u->leftjoin('shop_price_measure spm', 'sp.id = spm.id_price');
$u->where('sp.id=?', $id);
$u->fetchOne();


if ($u->id) {
    $product['id'] = $u->id;
    $product['isActive'] = (bool)($u->enabled === 'Y');
    $product['code'] = $u->code;
    $product['name'] = $u->name;
    $product['article'] = $u->article;
    $product['sortIndex'] = (int) $u->sort;
    $product['idGroup'] = $u->idGroup;
    $product['idType'] = $u->id_type;
    $product['nameGroup'] = $u->nameGroup;
    $product['price'] = (float)$u->price;
    $product['pricePurchase'] = (float)$u->price_purchase;
    $product['priceMiniWholesale'] = (float)$u->price_opt;
    $product['priceWholesale'] = (float)$u->price_opt_corp;
    $product['measurement'] = $u->measure;
    $product['currency'] = $u->curr;
    $product['bonus'] = (float)$u->bonus;
    $product['rate'] = (float)$u->rate;
    $product['tax'] = (float)$u->nds;
    $product['isInfinitely'] = true;
    if ($u->presence_count != '' && $u->presence_count >= 0) {
        $product['count'] = (float)$u->presence_count;
        $product['isInfinitely'] = false;
    }
    $product['stepCount'] = (float)$u->step_count;
    $product['minCount'] = (float)$u->min_count;
    $product['presence'] = $u->presence;
    $product['isNew'] = (bool)($u->flag_new === 'Y');
    $product['isHit'] = (bool)($u->flag_hit === 'Y');
    $product['isAction'] = (bool)($u->unsold === 'Y');
    $product['isYAMarket'] = (bool)$u->is_market;
    $product['manufacturer'] = $u->manufacturer;
    $product['idManufacturer'] = $u->id_manufacturer;
    if ($u->date_manufactured)
        $product['dateManufactured'] = date('Y-m-d', strtotime($u->date_manufactured));
    if ($u->id_brand) {
        $brand['id'] = $u->id_brand;
        $brand['name'] = $u->nameBrand;
        $product['brand'] = $brand;
    }
    $product['volume'] = (float)$u->volume;
    $product['weight'] = (float)$u->weight;
    $product['isDiscount'] = (bool)($u->discount === 'Y');
    $product['maxDiscount'] = (float)$u->max_discount;
    $product['description'] = $u->note;
    $product['fullDescription'] = $u->text;
    $product['seoHeader'] = $u->title;
    $product['seoKeywords'] = $u->keywords;
    $product['seoDescription'] = $u->description;
    $product['idModificationGroupDef'] = $u->id_modification_group_def;
    $product['idYAMarketCategory'] = $u->market_category;

    $product["idWeightView"] = (int)$u->id_weight_view;
    $product["idWeightEdit"] = (int)$u->id_weight_edit;
    $product["idVolumeView"] = (int)$u->id_volume_view;
    $product["idVolumeEdit"] = (int)$u->id_volume_edit;

    // скидки
    $product['discounts'] = getDiscounts($u->id);
    // картинки
    getImages($u->id, $product);
    // модификации
    getModifications($json->ids, $product);
    // спецификации
    getSpecifications($u->id, $product);
    // похожие товары
    getSimilarProducts($u->id, $product);
    // сопутствующие товары
    getAccompanyingProducts($u->id, $product);
    // комментарии
    getComments($u->id, $product);
    // перекрестные группы
    getCrossGroups($u->id, $product);
    // файлы
    getFiles($u->id, $product);
    // опции
    getOptions($u->id, $product);

    $product['isDiscountAllowed'] = $product['isDiscount'] && count($product["discounts"]);
    $items[] = $product;
}

$data['count'] = sizeof($items);
$data['items'] = $items;

if (se_db_error()) {
    $status['status'] = 'error';
    $status['error'] = 'Не удаётся получить информацию о товаре!';
} else {
    $status['status'] = 'ok';
    $status['data'] = $data;
}
outputData($status);