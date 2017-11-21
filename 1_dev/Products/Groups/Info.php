<?php

function getDiscounts($id)
{
    $answer = array();
    $u = new seTable('shop_discounts', 'sd');
    $u->select('sd.*');
    $u->innerjoin('shop_discount_links sdl', 'sdl.discount_id = sd.id');
    $u->where('sdl.id_group=?', $id);
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

function getDeliveries($id)
{
    $answer = array();
    return $answer;
}

function getLinksGroups($id)
{
    $answer = array();

    if (CORE_VERSION == "5.3") {
        $u = new seTable('shop_price_group', 'spg');
        $u->select('sp.id, sp.name');
        $u->innerjoin('shop_price sp', 'sp.id = spg.id_price');
        $u->where('spg.id_group = ? AND NOT spg.is_main', $id);
    } else {
        $u = new seTable('shop_crossgroup', 'scg');
        $u->select('sg.id, sg.name');
        $u->innerjoin('shop_group sg', 'scg.group_id = sg.id');
        $u->orderby();
        $u->where('scg.id=?', $id);
    }

    $items = $u->getList();
    foreach ($items as $item) {
        $linkGroup = null;
        $linkGroup['id'] = $item['id'];
        $linkGroup['name'] = $item['name'];
        $answer[] = $linkGroup;
    }

    return $answer;
}

function getSimilarGroups($id)
{
    $similarities = array();

    $u = new seTable('shop_group_related', 'sr');
    $u->select('sg1.id id1, sg2.id id2, sg1.name name1, sg2.name name2');
    $u->innerJoin('shop_group sg1', 'sr.id_group = sg1.id');
    $u->innerJoin('shop_group sg2', 'sr.id_related = sg2.id');
    $u->where('(sr.id_group = ? OR sr.id_related = ?) AND sr.type = 1', $id);
    $objects = $u->getList();
    foreach ($objects as $item) {
        $similar = null;
        $i = 1;
        if ($item['id1'] == $id)
            $i = 2;
        $similar['id'] = $item['id' . $i];
        $similar['name'] = $item['name' . $i];
        $similarities[] = $similar;
    }

    return $similarities;
}

function getAdditionalSubgroups($id)
{
    $u = new seTable('shop_group_related', 'sr');
    $u->select('sg.id, sg.name');
    $u->innerJoin('shop_group sg', 'sr.id_related = sg.id');
    $u->where('sr.id_group = ? AND sr.type = 3', $id);

    return  $u->getList();;
}


function translate($name)
{
    if (strcmp($name, "price") === 0)
        return "Цена";
    if (strcmp($name, "brand") === 0)
        return "Бренды";
    if (strcmp($name, "flag_hit") === 0)
        return "Хиты";
    if (strcmp($name, "flag_new") === 0)
        return "Новинки";
    return $name;
}

function getFilterParams($id)
{
    $answer = array();

    $u = new seTable('shop_group_filter', 'sgf');
    $u->select('sgf.*, sf.name');
    $u->leftjoin('shop_feature sf', 'sf.id = sgf.id_feature');
    $u->where('sgf.id_group=?', $id);
    $u->orderby('sort');
    $items = $u->getList();

    foreach ($items as $item) {
        $filter = null;
        $filter['id'] = $item['id_feature'];
        $filter['name'] = $item['name'];
        if (empty($filter['name']))
            $filter['name'] = translate($item['default_filter']);
        $filter['code'] = $item['default_filter'];
        $filter['sortIndex'] = (int)$item['sort'];
        $filter['isActive'] = (bool)$item['expanded'];
        $answer[] = $filter;
    }
    return $answer;
}

function getImages($id, &$group)
{

    global $json;

    $u = new seTable('shop_group_img', 'sgi');
    $u->where('sgi.id_group=?', $id);
    $u->orderby("sort");
    $objects = $u->getList();

    foreach ($objects as $item) {
        $image = null;
        $image['id'] = $item['id'];
        $image['imageFile'] = $item['picture'];
        $image['imageAlt'] = $item['picture_alt'];
        $image['sortIndex'] = $item['sort'];
        if ($image['imageFile']) {
            if (strpos($image['imageFile'], "://") === false) {
                $image['imageUrl'] = 'http://' . $json->hostname . "/images/rus/shopgroup/" . $image['imageFile'];
                $image['imageUrlPreview'] = "http://{$json->hostname}/lib/image.php?size=64&img=images/rus/shopgroup/" . $image['imageFile'];
            } else {
                $image['imageUrl'] = $image['imageFile'];
                $image['imageUrlPreview'] = $image['imageFile'];
            }
        }
        $group['images'][] = $image;
    }
}

if (empty($json->ids))
    $json->ids[] = $_GET['id'];
$ids = implode(",", $json->ids);

$u = new seTable('shop_group', 'sg');
if (CORE_VERSION == "5.3") {
    $u->select('sg.*, GROUP_CONCAT(CONCAT_WS(\':\', sgtp.level, sgt.id_parent) SEPARATOR \';\') ids_parents');
    $u->leftjoin("shop_group_tree sgt", "sgt.id_child = sg.id AND sg.id <> sgt.id_parent");
    $u->leftjoin("shop_group_tree sgtp", "sgtp.id_child = sgt.id_parent");
} else {
    $u->select('sg.*, sgp.name nameParent');
    $u->leftjoin('shop_group sgp', 'sgp.id = sg.upid');
}

$u->where('sg.id in (?)', $ids);
$u->groupby('sg.id');
$result = $u->getList();

$items = array();
foreach ($result as $item) {
    $group = null;
    $group['id'] = $item['id'];
    $group['isActive'] = (bool)($item['active'] == 'Y');
    if (CORE_VERSION == "5.3") {
        if ($item['ids_parents']) {
            $idsLevels = explode(";", $item['ids_parents']);
            $idParent = 0;
            $level = 0;
            foreach ($idsLevels as $idLevel) {
                $ids = explode(":", $idLevel);
                if ($ids[0] >= $level) {
                    $idParent = $ids[1];
                    $level = $ids[0];
                }
            }
            $group['idParent'] = $idParent;
        }
    } else {
        if ($item['upid'] != $item['id'] && $item['upid'] != '0')
            $group['idParent'] = $item['upid'];
    }
    $group['code'] = $item['code_gr'];
    $group['name'] = $item['name'];
    $group['nameParent'] = $item['nameParent'];
    $group['description'] = $item['commentary'];
    $group['note'] = $item['description'];
    $group['fullDescription'] = $item['footertext'];
    $group['imageFile'] = $item['picture'];
    $group['imageAlt'] = $item['picture_alt'];
    $group['discounts'] = getDiscounts($item['id']);
    $group['deliveries'] = getDeliveries($item['id']);
    $group['seoHeader'] = $item['title'];
    $group['seoKeywords'] = $item['keywords'];
    $group['seoDescription'] = $item['description'];
    $group['breadCrumb'] = $item['bread_crumb'];
    $group['sortIndex'] = (int)$item['position'];
    $group['idModificationGroupDef'] = $item['id_modification_group_def'];
    $group['linksGroups'] = getLinksGroups($item['id']);
    $group['similarGroups'] = getSimilarGroups($item['id']);
    $group['additionalSubgroups'] = getAdditionalSubgroups($item['id']);
    $group['parametersFilters'] = getFilterParams($item['id']);
    $group['discounts'] = getDiscounts($item['id']);
    if ($group['imageFile']) {
        if (strpos($group['imageFile'], "://") === false) {
            $group['imageUrl'] = 'http://' . $json->hostname . "/images/rus/shopgroup/" . $group['imageFile'];
            $group['imageUrlPreview'] = "http://{$json->hostname}/lib/image.php?size=64&img=images/rus/shopgroup/" . $group['imageFile'];
        } else {
            $group['imageUrl'] = $group['imageFile'];
            $group['imageUrlPreview'] = $group['imageFile'];
        }
    }
    getImages($item['id'], $group);

    if ($_SESSION['isIncPrices']) {
        $u = new seTable('shop_group_inc_price', 'sgi');
        $u->select("sgi.*");
        $u->where('sgi.id_group = ?', $group["id"]);
        $result = $u->fetchOne();
        if ($result) {
            $group["incPrice"] = $result["price"];
            $group["incPriceOpt"] = $result["price_opt"];
            $group["incPriceCorp"] = $result["price_opt_corp"];
        }
    }

    $items[] = $group;
}

$data['count'] = sizeof($items);
$data['items'] = $items;

if (se_db_error()) {
    $status['status'] = 'error';
    $status['error'] = 'Не удаётся прочитать информацию о категории товаров!';
} else {
    $status['status'] = 'ok';
    $status['data'] = $data;
}

outputData($status);
