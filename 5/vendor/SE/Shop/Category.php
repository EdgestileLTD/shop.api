<?php

namespace SE\Shop;

class Category extends Base
{
    protected $tableName = "shop_group";
    protected $sortOrder = "asc";
    protected $limit = null;

    private function getPlainTree($items, $idParent = null)
    {
        $result = array();
        foreach ($items as $item) {
            if ($item["idParent"] == $idParent) {
                $result[] = $item;
                $result = array_merge($result, $this->getPlainTree($items, $item["id"]));
            }
        }
        return $result;
    }

    private function getParentItem($item, $items)
    {
        foreach ($items as $it)
            if ($it["id"] == $item["idParent"])
                return $it;
    }

    private function getPathName($item, $items)
    {
        if (!$item["idParent"])
            return $item["name"];

        $parent = $this->getParentItem($item, $items);
        if (!$parent)
            return $item["name"];
        return $this->getPathName($parent, $items) . "/" . $item["name"];
    }

    private function getGridTree($items)
    {
        foreach ($items as &$item) {
            $item["pathName"] = $this->getPathName($item, $items);
            $item["level"] = substr_count($item["pathName"], "/");
        }
        return $items;
    }

    protected function getSettingsFetch()
    {
        if (CORE_VERSION == "5.3") {
            $result["select"] = "sg.id, GROUP_CONCAT(CONCAT_WS(':', sgtp.level, sgt.id_parent) SEPARATOR ';') ids_parents,
                sg.code_gr, sg.position, sg.name, sg.picture, sg.picture_alt, sg.id_modification_group_def,
                sg.description, sgt.id_parent";
            $joins[] = array(
                "type" => "left",
                "table" => 'shop_group_tree sgt',
                "condition" => 'sgt.id_child = sg.id AND sg.id <> sgt.id_parent'
            );
            $joins[] = array(
                "type" => "left",
                "table" => 'shop_group_tree sgtp',
                "condition" => 'sgtp.id_child = sgt.id_parent'
            );
            $result["joins"] = $joins;
        }
        else {
            $result["select"] = "sg.id, sg.upid id_parent, sg.code_gr code, 
                sg.position sort_index, sg.name, sg.picture, sg.picture_alt, 
                sg.id_modification_group_def,
                sg.description, (SELECT COUNT(*) FROM `shop_group` WHERE upid = sg.id) gcount,
                (SELECT COUNT(*) FROM `shop_price` WHERE sg.id = id_group) count_goods";
        }
        return $result;
    }

    protected function correctValuesBeforeFetch($items = array())
    {
        $result = array();
        $limit = $this->input["limit"];
        if ($limit) {
            $items = $this->getPlainTree($items);
            $items = $this->getGridTree($items);
            $count = count($items);
            $offset = $this->offset;
            if ($limit > $count)
                $limit = $count;
            for ($i = $offset; $i < ($offset + $limit); ++$i)
                $result[] = $items[$i];
        } else $result = $items;
        return $result;
    }

    protected function getChilds()
    {        
        $idParent = $this->input["id"];
        $filter = array("field" => "idParent", "value" =>  $idParent);
        $category = new Category(array("filters" => $filter));
        return $category->fetch();
    }

    protected function getAddInfo()
    {
        return array("childs" => $this->getChilds());
    }
}