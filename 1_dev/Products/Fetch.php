<?php

function getIdsChildGroups($idParent)
{
    $result = array($idParent);
    $u = new seTable('shop_group', 'sg');
    $u->select('sg.id');
    $u->where('sg.upid=?', $idParent);
    $objects = $u->getList();
    foreach ($objects as $item)
        $result = array_merge($result, getIdsChildGroups($item['id']));
    return $result;
}

// преоброзование перемнных запроса в перемнные БД
function convertFields($str)
{
    $str = str_replace('idGroup ', 'sg.id ', $str);
    $str = str_replace('[id]', 'sp.id ', $str);
    $str = str_replace('[idGroup]', 'sg.id ', $str);
    if (CORE_VERSION == "5.3")
        $str = str_replace('[idCrossGroup]', 'spg_.id_group ', $str);
    else $str = str_replace('[idCrossGroup]', 'spg.group_id ', $str);
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

if (CORE_VERSION == "5.3")
    se_db_query("UPDATE shop_group sg SET sg.scount = 
      (SELECT COUNT(*) FROM shop_price_group spg INNER JOIN shop_price sp ON sp.id = spg.id_price WHERE spg.id_group = sg.id AND sp.enabled = 'Y')");
else
    se_db_query("UPDATE shop_group sg SET sg.scount =
                 (SELECT COUNT(*) FROM shop_price sp WHERE sp.id_group = sg.id AND sp.enabled = 'Y')");

$crossGroup = 'spg.id isCrossGroup';
if (CORE_VERSION == "5.3")
    $crossGroup = '0 isCrossGroup';

$u = new seTable('shop_price', 'sp');
$u->select("sp.*, sg.name namegroup, sdl.discount_id is_discount, 
            COUNT(DISTINCT(smf.id_modification)) as countModifications, sb.name nameBrand, {$crossGroup}");
if (CORE_VERSION == "5.3") {
    $u->leftjoin("shop_price_group spg", "spg.id_price = sp.id AND spg.is_main");
    $u->leftjoin('shop_group sg', 'sg.id = spg.id_group');
    $u->leftjoin("shop_price_group spg_", "spg_.id_price = sp.id AND spg_.is_main <> 1");
} else {
    $u->leftjoin('shop_group sg', 'sg.id=sp.id_group');
    $u->leftjoin('shop_group_price spg', 'sp.id = spg.price_id');
}
$u->leftjoin('shop_discount_links sdl', 'sdl.id_price=sp.id');
$u->leftjoin('(SELECT smf.id_price, smf.id_modification FROM shop_modifications_feature smf
                           WHERE NOT smf.id_value IS NULL AND NOT smf.id_modification IS NULL GROUP BY smf.id_modification) smf', 'sp.id=smf.id_price');
$u->leftjoin('shop_brand sb', 'sb.id = sp.id_brand');
if ($json->filter && strpos($json->filter, '[idModificationGroup]')) {
    $u->leftjoin('shop_modifications sm', 'sm.id_price = sp.id');
    $u->leftjoin('shop_modifications_group smg', 'smg.id = sm.id_mod_group');
}

$searchStr = $json->searchText;
$searchArr = explode(' ', $searchStr);

if (!empty($json->idGroup) && (CORE_VERSION != "5.3"))
    $filterGroups = 'sg.id IN (' . implode(",", getIdsChildGroups($json->idGroup));
if (!empty($json->filter))
    $filter = convertFields($json->filter);
if (!empty($searchStr)) {
    if (strpos($searchStr, '?') === 0) {
        $search = substr($searchStr, 1);
        $search = convertFields($search);
    } else {
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
if ($json->filter && strpos($json->filter, '[idGroup]')) {

}

if (!empty($where))
    $u->where($where);
$u->groupby('sp.id');

$count = $u->getListCount();

$patterns = array('id' => 'sp.id',
    'isActiveIco' => 'enabled',
    'isNewIco' => 'flag_new',
    'isHitIco' => 'flag_hit',
    'isYAMarketIco' => 'is_market',
    'code' => 'sp.code',
    'article' => 'sp.article',
    'name' => 'sp.name',
    'price' => 'sp.price',
    'count' => 'presence_count',
    'nameGroup' => 'namegroup',
    'brand' => 'sb.name',
    'weight' => 'sp.weight',
    'volume' => 'sp.volume',
    'sortIndex' => 'sort'
);
$sortBy = (isset($patterns[$json->sortBy])) ? $patterns[$json->sortBy] : 'id';
$u->orderby($sortBy, $json->sortOrder === 'desc');
$objects = $u->getList($json->offset, $json->limit);

foreach ($objects as $item) {
    $product = null;
    $product['id'] = $item['id'];
    $product['code'] = $item['code'];
    $product['article'] = $item['article'];
    $product['name'] = $item['name'];
    $product['sortIndex'] = (int) $item['sort'];
    $product['nameGroup'] = $item['namegroup'];
    $product['manufacture'] = $item['manufacturer'];
    $product['price'] = (real)$item['price'];
    $product['pricePurchase'] = (real)$item['price_purchase'];
    $product['currency'] = $item['curr'];
    $product['imageFile'] = $item['imageFile'];
    $product['imageAlt'] = $item['imageAlt'];
    if ($product['imageFile']) {
        if (strpos($product['imageFile'], '://') === false) {
            $product['imageUrl'] = 'http://' . $json->hostname . "/images/rus/shopprice/" . $product['imageFile'];
            $product['imageUrlPreview'] = "http://{$json->hostname}/lib/image.php?size=64&img=images/rus/shopprice/" . $image['imageFile'];
        } else {
            $product['imageUrl'] = $product['imageFile'];
            $product['imageUrlPreview'] = $product['imageFile'];
        }
    }
    $product['measurement'] = $item['measure'];
    $product['specialPricePercent'] = (real)$item['spec'];
    $product['isActive'] = $item['enabled'] == 'Y';
    $product['isInfinitely'] = true;
    $product['countModifications'] = (int)$item['countModifications'];
    if (!empty($item['presence_count']) && $item['presence_count'] >= 0) {
        $product['count'] = (float)$item['presence_count'];
        $product['isInfinitely'] = false;
    }
    $product['presence'] = $item['presence'];
    $product['isNew'] = (bool)($item['flag_new'] === 'Y');
    $product['isHit'] = (bool)($item['flag_hit'] === 'Y');
    $product['isAction'] = (bool)($item['unsold'] === 'Y');
    $product['isDiscountAllowed'] = ($item['discount'] === 'Y') && ($item['is_discount']);
    $product['isYAMarket'] = (bool)$item['is_market'];
    $product['volume'] = (float)$item["volume"];
    $product['weight'] = (float)$item["weight"];
    $brand['id'] = $item['id_brand'];
    $brand['name'] = $item['nameBrand'];
    $product['brand'] = $brand;
    $product['isCrossGroup'] = (bool)!empty($item['isCrossGroup']);

    $items[] = $product;
}

$data['count'] = $count;
$data['items'] = $items;

$status = array();
if (!se_db_error()) {
    $status['status'] = 'ok';
    $status['data'] = $data;
} else {
    $status['status'] = 'error';
    $status['error'] = se_db_error();// 'Не удаётся получить список товаров!';
}

outputData($status);