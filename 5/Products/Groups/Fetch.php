<?php

function getPlainTree($items, $idParent = null)
{
    $result = array();
    foreach ($items as $item) {
        if ($item["idParent"] == $idParent) {
            $result[] = $item;
            $result = array_merge($result, getPlainTree($items, $item["id"]));
        }
    }
    return $result;
}

function getParentItem($item, $items)
{
    foreach ($items as $it)
        if ($it["id"] == $item["idParent"])
            return $it;
}

function getPathName($item, $items)
{
    if (!$item["idParent"])
        return $item["name"];

    $parent = getParentItem($item, $items);
    if (!$parent)
        return $item["name"];
    return getPathName($parent, $items) . "/" . $item["name"];
}

function getGridTree($items)
{   
    foreach ($items as &$item) 
        $item["pathName"] = getPathName($item, $items);
    return $items;
}

function convertFields($str)
{
    $str = str_replace('[idParent]', 'sgt.id_parent ', $str);
    return $str;
}

function calcCountGoods(&$items, $idParent = null)
{
    $count = 0;
    foreach ($items as &$item) {
        if ($item["idParent"] == $idParent) {
            $item["countGoods"] += calcCountGoods($items, $item["id"]);
            $count += $item["countGoods"];
        }
    }
    return $count;
}

$u = new seTable('shop_group', 'sg');
if (CORE_VERSION == "5.3") {
    $u->select("sg.id,  GROUP_CONCAT(CONCAT_WS(':', sgtp.level, sgt.id_parent) SEPARATOR ';') ids_parents,
                sg.code_gr, sg.position, sg.name, sg.picture, sg.picture_alt, sg.id_modification_group_def,
                sg.description");
    $u->leftjoin("shop_group_tree sgt", "sgt.id_child = sg.id AND sg.id <> sgt.id_parent");
    $u->leftjoin("shop_group_tree sgtp", "sgtp.id_child = sgt.id_parent");
} else {
    $u->select("sg.id, sg.upid, sg.code_gr, sg.position, sg.name, sg.picture, sg.picture_alt, sg.id_modification_group_def,
            sg.description, (SELECT COUNT(*) FROM `shop_group` WHERE upid=sg.id) AS gcount,
            (SELECT COUNT(*) FROM `shop_price` WHERE sg.id=id_group) countGoods");
}
$u->groupby('sg.id');

$searchStr = $json->searchText;
$searchArr = explode(' ', $searchStr);

if (!empty($json->filter))
    $filter = convertFields($json->filter);
if (!empty($searchStr)) {
    if (strpos($searchStr, '?') === 0) {
        $search = substr($searchStr, 1);
        $search = convertFields($search);
    } else {
        foreach ($searchArr as $searchItem) {
            if (!empty($search))
                $search .= " AND ";
            $search .= "(sg.name like '%$searchItem%' OR sg.code_gr like '%$searchItem%'
                        OR sg.title like '%$searchItem%')";
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

if (!empty($where))
    $u->where($where);

$u->orderby('position');

$objects = $u->getList();

foreach ($objects as $item) {
    if ($item['code_gr'] == 'parser')
        continue;

    $group = null;
    $group['id'] = $item['id'];
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
    $group['sortIndex'] = (int)$item['position'];
    $group['imageFile'] = $item['picture'];
    $group['imageAlt'] = $item['picture_alt'];
    $group['note'] = $item['description'];
    $group['grCount'] = $item['gcount'];
    $group['countGoods'] = $item['countGoods'];
    $group['idModificationGroupDef'] = $item['id_modification_group_def'];
    $items[] = $group;
}

$items = getPlainTree($items);
$items = getGridTree($items);
$data['count'] = count($items);

$limit = $json->limit;
$offset = $json->offset;
if ($limit > $data['count'])
    $limit = $data['count'];
for ($i = $offset; $i < ($offset + $limit); ++$i)
    $data["items"][] = $items[$i];

$status = array();
if (!se_db_error()) {
    $status['status'] = 'ok';
    $status['data'] = $data;
} else {
    $status['status'] = 'error';
    $status['errortext'] = 'Не удаётся прочитать список групп товаров';
}

outputData($status);