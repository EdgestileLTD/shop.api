<?php

namespace SE\Shop;

use SE\DB;
use SE\Exception;

class Category extends Base
{
    protected $tableName = "shop_group";
    protected $sortOrder = "asc";
    protected $sortBy = "position";
    protected $limit = null;
    protected $allowedSearch = false;

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
        return $this->getPathName($parent, $items) . " / " . $item["name"];
    }

    public function getPatches($items)
    {
        $result = array();
        $search = strtolower($this->input["searchText"]);
        foreach ($items as $item) {
            if (empty($search) || mb_strpos(strtolower($item["name"]), $search) !== false) {
                $item["name"] = $this->getPathName($item, $items);
                $item["level"] = substr_count($item["name"], "/");
                $result[] = $item;
            }
        }
        return $result;
    }

    private function getTreeView($items, $idParent = null)
    {
        $result = array();
        foreach ($items as $item) {
            if ($item["idParent"] == $idParent) {
                $item["childs"] = $this->getTreeView($items, $item["id"]);
                $result[] = $item;
            }
        }
        return $result;
    }

    private function setIdMainParent($items)
    {
        $result = array();
        foreach ($items as $item) {
            if ($item['idsParents']) {
                $idsLevels = explode(";", $item['idsParents']);
                $idParent = 0;
                $level = 0;
                foreach ($idsLevels as $idLevel) {
                    $ids = explode(":", $idLevel);
                    if ($ids[0] >= $level) {
                        $idParent = $ids[1];
                        $level = $ids[0];
                    }
                }
                $item['idParent'] = $idParent;
            }
            $result[] = $item;
        }
        return $result;
    }

    protected function getSettingsFetch()
    {
        if (CORE_VERSION == "5.3") {
            $result["select"] = "sg.id, GROUP_CONCAT(CONCAT_WS(':', sgtp.level, sgt.id_parent) SEPARATOR ';') ids_parents,
                sg.code_gr, sg.position, sg.name, sg.picture imageFile, sg.picture_alt imageAlt, sg.id_modification_group_def,
                sg.description, sgt.level level, sgt.id_parent id_parent";
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
        } else {
            $result["select"] = "sg.id, sg.upid id_parent, sg.code_gr code, 
                sg.position sort_index, sg.name, sg.picture imageFile, sg.picture_alt imageAlt, 
                sg.id_modification_group_def,
                sg.description, (SELECT COUNT(*) FROM `shop_group` WHERE upid = sg.id) gcount,
                (SELECT COUNT(*) FROM `shop_price` WHERE sg.id = id_group) count_goods";
        }
        return $result;
    }

    protected function getSettingsInfo()
    {
        return $this->getSettingsFetch();
    }

    protected function correctValuesBeforeFetch($items = array())
    {
        if (CORE_VERSION == "5.3")
            $items = $this->setIdMainParent($items);
        if ($this->input["isTree"] && empty($this->input["searchText"]))
            $result = $this->getTreeView($items);
        else $result = $this->getPatches($items);
        return $result;
    }

    public function getDiscounts($idCategory = null)
    {
        $result = array();
        $id = $idCategory ? $idCategory : $this->input["id"];
        if (!$id)
            return $result;

        $u = new DB('shop_discounts', 'sd');
        $u->select('sd.*');
        $u->innerJoin('shop_discount_links sdl', 'sdl.discount_id = sd.id');
        $u->where('sdl.id_group = ?', $id);
        $u->orderBy('sd.id');
        return $u->getList();
    }

    public function getImages($idCategory = null)
    {
        $result = array();
        $id = $idCategory ? $idCategory : $this->input["id"];
        if (!$id)
            return $result;

        $u = new DB('shop_group_img', 'si');
        $u->where('si.id_group = ?', $id);
        $u->orderBy("sort");
        $objects = $u->getList();

        foreach ($objects as $item) {
            $image = null;
            $image['id'] = $item['id'];
            $image['imageFile'] = $item['picture'];
            $image['imageAlt'] = $item['pictureAlt'];
            $image['sortIndex'] = $item['sort'];
            if ($image['imageFile']) {
                if (strpos($image['imageFile'], "://") === false) {
                    $image['imageUrl'] = 'http://' . HOSTNAME . "/images/rus/shopgroup/" . $image['imageFile'];
                    $image['imageUrlPreview'] = "http://" . HOSTNAME . "/lib/image.php?size=64&img=images/rus/shopgroup/" . $image['imageFile'];
                } else {
                    $image['imageUrl'] = $image['imageFile'];
                    $image['imageUrlPreview'] = $image['imageFile'];
                }
            }
            if (empty($product["imageFile"]) && $image['isMain']) {
                $product["imageFile"] = $image['imageFile'];
                $product["imageAlt"] = $image['imageAlt'];
            }
            $result[] = $image;
        }
        return $result;
    }

    public function getDeliveries($idCategory = null)
    {
        $result = array();
        $id = $idCategory ? $idCategory : $this->input["id"];
        if (!$id)
            return $result;

        return $result;
    }

    public function getLinksGroups($idCategory = null)
    {
        $result = array();
        $id = $idCategory ? $idCategory : $this->input["id"];
        if (!$id)
            return $result;

        if (CORE_VERSION == "5.3") {
            $u = new DB('shop_price_group', 'spg');
            $u->select('sp.id, sp.name');
            $u->innerJoin('shop_price sp', 'sp.id = spg.id_price');
            $u->where('spg.id_group = ? AND NOT spg.is_main', $id);
        } else {
            $u = new DB('shop_crossgroup', 'scg');
            $u->select('sg.id, sg.name');
            $u->innerJoin('shop_group sg', 'scg.group_id = sg.id');
            $u->orderBy();
            $u->where('scg.id = ?', $id);
        }

        $items = $u->getList();
        foreach ($items as $item) {
            $linkGroup = null;
            $linkGroup['id'] = $item['id'];
            $linkGroup['name'] = $item['name'];
            $result[] = $linkGroup;
        }

        return $result;
    }

    private function translate($name)
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

    public function getFilterParams($idCategory = null)
    {
        $result = array();
        $id = $idCategory ? $idCategory : $this->input["id"];
        if (!$id)
            return $result;

        $u = new DB('shop_group_filter', 'sgf');
        $u->select('sgf.*, sf.name');
        $u->leftJoin('shop_feature sf', 'sf.id = sgf.id_feature');
        $u->where('sgf.id_group = ?', $id);
        $u->orderBy('sgf.sort');
        $items = $u->getList();

        foreach ($items as $item) {
            $filter = null;
            $filter['id'] = $item['idFeature'];
            $filter['name'] = $item['name'];
            if (empty($filter['name']))
                $filter['name'] = $this->translate($item['defaultFilter']);
            $filter['code'] = $item['defaultFilter'];
            $filter['sortIndex'] = (int)$item['sort'];
            $filter['isActive'] = (bool)$item['expanded'];
            $result[] = $filter;
        }
        return $result;
    }


    protected function getChilds()
    {
        $idParent = $this->input["id"];
        if (CORE_VERSION == "5.3") {
            $filter = array(
                array("field" => "idParent", "value" => $idParent),
                array("field" => "level", "value" => ++$this->result["level"]));
            $category = new Category(array("filters" => $filter));
            $result = $category->fetch();
        } else {
            $filter = array("field" => "idParent", "value" => $idParent);
            $category = new Category(array("filters" => $filter));
            $result = $category->fetch();
        }
        return $result;
    }

    protected function getAddInfo()
    {
        $result["discounts"] = $this->getDiscounts();
        $result["images"] = $this->getImages();
        $result["deliveries"] = $this->getDeliveries();
        $result['linksGroups'] = $this->getLinksGroups();
        $result['parametersFilters'] = $this->getFilterParams();
        $result["childs"] = $this->getChilds();
        return $result;
    }

    private function saveDiscounts()
    {
        try {
            foreach ($this->input["ids"] as $id)
                DB::saveManyToMany($id, $this->input["discounts"],
                    array("table" => "shop_discount_links", "key" => "id_group", "link" => "discount_id"));
        } catch (Exception $e) {
            $this->error = "Не удаётся сохранить скидки категории товара!";
            throw new Exception($this->error);
        }
    }

    private function saveImages()
    {
        try {
            $idsGroups = $this->input["ids"];
            $images = $this->input["images"];
            $idsStore = "";
            foreach ($images as $image) {
                if ($image["id"] > 0) {
                    if (!empty($idsStore))
                        $idsStore .= ",";
                    $idsStore .= $image["id"];
                    $u = new DB('shop_group_img', 'si');
                    $image["picture"] = $image["imageFile"];
                    $image["sort"] = $image["sortIndex"];
                    $image["pictureAlt"] = $image["imageAlt"];
                    $u->setValuesFields($image);
                    $u->save();
                }
            }

            $idsStr = implode(",", $idsGroups);
            if (!empty($idsStore)) {
                $u = new DB('shop_group_img', 'si');
                $u->where("id_group IN ($idsStr) AND NOT (id IN (?))", $idsStore)->deleteList();
            } else {
                $u = new DB('shop_group_img', 'si');
                $u->where('id_group IN (?)', $idsStr)->deleteList();
            }

            $data = array();
            foreach ($images as $image)
                if (empty($image["id"]) || ($image["id"] <= 0)) {
                    foreach ($idsGroups as $idProduct) {
                        $data[] = array('id_group' => $idProduct, 'picture' => $image["imageFile"],
                            'sort' => (int)$image["sortIndex"], 'picture_alt' => $image["imageAlt"]);
                        $newImages[] = $image["imageFile"];
                    }
                }

            if (!empty($data))
                DB::insertList('shop_group_img', $data);
        } catch (Exception $e) {
            $this->error = "Не удаётся сохранить изображения категории товара!";
            throw new Exception($this->error);
        }
    }

    private function saveLinksGroups()
    {
        try {
            $idsGroups = $this->input["ids"];
            $links = $this->input["linksGroups"];
            $idsExists = array();
            foreach ($links as $group)
                if ($group["id"])
                    $idsExists[] = $group["id"];
            if (CORE_VERSION != "5.3")
                $idsExists = array_diff($idsExists, $idsGroups);
            $idsExistsStr = implode(",", $idsExists);
            $idsStr = implode(",", $idsGroups);

            if (CORE_VERSION == "5.3") {
                $u = new DB('shop_price_group', 'spg');
                if ($idsExistsStr)
                    $u->where("(NOT id_price IN ({$idsExistsStr})) AND id_group IN (?)", $idsStr)->deleteList();
                else $u->where('id_group IN (?)', $idsStr)->deleteList();
                $idsExists = array();
                if ($idsExistsStr) {
                    $u->select("id_price, id_group");
                    $u->where("(id_price IN ({$idsExistsStr})) AND id_group IN (?)", $idsStr);
                    $objects = $u->getList();
                    foreach ($objects as $item)
                        $idsExists[] = $item["idPrice"];
                };
                $data = array();
                foreach ($links as $group)
                    if (empty($idsExists) || !in_array($group["id"], $idsExists))
                        foreach ($idsGroups as $idGroup)
                            $data[] = array('id_price' => $group["id"], 'id_group' => $idGroup, 'is_main' => 0);
                if (!empty($data))
                    DB::insertList('shop_price_group', $data);
            } else {
                $u = new DB('shop_crossgroup', 'scg');
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
                        $idsExists[] = $item["groupId"];
                    }
                };
                $data = array();
                foreach ($links as $group)
                    if (empty($idsExists) || !in_array($group["id"], $idsExists))
                        foreach ($idsGroups as $idGroup)
                            $data[] = array('id' => $idGroup, 'group_id' => $group['id']);
                if (!empty($data))
                    DB::insertList('shop_crossgroup', $data);
            }
        } catch (Exception $e) {
            $this->error = "Не удаётся сохранить связанные категории!";
            throw new Exception($this->error);
        }
    }

    private function saveParametersFilters()
    {
        try {
            $idsGroups = $this->input["ids"];
            $filters = $this->input["parametersFilters"];

            $idsStr = implode(",", $idsGroups);
            $u = new DB('shop_group_filter', 'sgf');
            $u->where('id_group IN (?)', $idsStr)->deleteList();

            foreach ($filters as $filter) {
                foreach ($idsGroups as $idGroup)
                    if ($filter["id"] || !empty($filter["code"]))
                        $data[] = array('id_group' => $idGroup, 'id_feature' => $filter["id"],
                            'default_filter' => $filter["code"], 'expanded' => (int)$filter["isActive"],
                            'sort' => (int)$filter["sortIndex"]);
            }
            if (!empty($data)) {
                DB::insertList('shop_group_filter', $data);
            }
        } catch (Exception $e) {
            $this->error = "Не удаётся сохранить фильтры параметров!";
            throw new Exception($this->error);
        }
    }

    private function getUrl($code, $id)
    {
        $code_n = $code;
        $id = (int)$id;
        $u = new DB('shop_group', 'sg');
        $i = 1;
        while ($i < 1000) {
            $data = $u->findList("sg.code_gr = '$code_n' AND id <> {$id}")->fetchOne();
            if ($data["id"])
                $code_n = $code . "-$i";
            else return $code_n;
            $i++;
        }
        return uniqid();
    }

    protected function correctValuesBeforeSave()
    {
        if (!$this->input["id"] && !$this->input["ids"] || isset($this->input["code"])) {
            if (empty($this->input["code"]))
                $this->input["code"] = strtolower(se_translite_url($this->input["name"]));
            $this->input["codeGr"] = $this->getUrl($this->input["code"], $this->input["id"]);
        }
        if (isset($this->input["idParent"]))
            $this->input["upid"] = $this->input["idParent"];
        if (isset($this->input["imageFile"]))
            $this->input["picture"] = $this->input["imageFile"];
        if (isset($this->input["sortIndex"]))
            $this->input["position"] = $this->input["sortIndex"];
    }

    protected function saveAddInfo()
    {
        $this->input["ids"] = empty($this->input["ids"]) ? array($this->input["id"]) : $this->input["ids"];
        if (!$this->input["ids"])
            return false;

        $this->saveDiscounts();
        $this->saveImages();
        $this->saveLinksGroups();
        $this->saveParametersFilters();

        return true;
    }
}