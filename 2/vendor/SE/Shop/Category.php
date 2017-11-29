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
    protected $treepath = array();

    private function getParentItem($item, $items)
    {
        foreach ($items as $it)
            if ($it["id"] == $item["upid"])
                return $it;
    }

    private function getPathName($item, $items)
    {
        if (!$item["upid"])
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
            if ($item["upid"] == $idParent) {
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
                $item['upid'] = $idParent;
            }
            $result[] = $item;
        }
        return $result;
    }

    protected function getSettingsFetch()
    {
        if (CORE_VERSION == "5.3") {
            $result["select"] = "sg.*, GROUP_CONCAT(CONCAT_WS(':', sgtp.level, sgt.id_parent) SEPARATOR ';') ids_parents,
                sgt.level level";
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
            $result["select"] = "sg.*";
        }
        return $result;
    }

    protected function getSettingsInfo()
    {
        return $this->getSettingsFetch();
    }

    public function info($id = null)
    {
        $result = parent::info();
        if (CORE_VERSION == "5.3") {
            $arr = $this->setIdMainParent(array($result));
            $this->result = $arr[0];
        }
        $this->result["nameParent"] = $this->getNameParent();
        return $this->result;
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

    public function getRelatedGroups($idCategory = null)
    {
        $result = array();
        $id = $idCategory ? $idCategory : $this->input["id"];
        if (!$id)
            return $result;

        $u = new DB('shop_group_related', 'sr');
        $u->select('sg1.id id, sg1.name name');
        //$u->select('sg1.id id1, sg2.id id2, sg1.name name1, sg2.name name2');
        //$u->innerJoin('shop_group sg1', 'sr.id_group = sg1.id');
        $u->innerJoin('shop_group sg1', 'sr.id_related = sg1.id');
        $u->where('sr.id_group = ?', $id);
        $objects = $u->getList();
        foreach ($objects as $item) {
            //$similar = null;
            //$i = 1;
            //if ($item['id1'] == $id)
            //    $i = 2;
            //$similar['id'] = $item['id1' . $i];
            //$similar['name'] = $item['name1' . $i];
            $result[] = $item;
        }

        return $result;
    }

    public function getLinksGroups($idCategory = null)
    {
        $result = array();
        $id = $idCategory ? $idCategory : $this->input["id"];
        if (!$id)
            return $result;

        $u = new DB('shop_crossgroup', 'scg');
        $u->select('sg.id, sg.name');
        $u->innerJoin('shop_group sg', 'scg.group_id = sg.id');
        $u->orderBy();
        $u->where('scg.id = ?', $id);

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
                array("field" => "upid", "value" => $idParent),
                array("field" => "level", "value" => ++$this->result["level"]));
            $category = new Category(array("filters" => $filter));
            $result = $category->fetch();
        } else {
            $filter = array("field" => "upid", "value" => $idParent);
            $category = new Category(array("filters" => $filter));
            $result = $category->fetch();
        }
        return $result;
    }

    private function getNameParent()
    {
        if (!$this->result["upid"])
            return null;

        $db = new DB("shop_group");
        $db->select("name");
        $result = $db->getInfo($this->result["upid"]);
        return $result["name"];
    }

    protected function getAddInfo()
    {
        $result["discounts"] = $this->getDiscounts();
        $result["images"] = $this->getImages();
        $result["deliveries"] = $this->getDeliveries();
        $result['linksGroups'] = $this->getLinksGroups();
        $result['parametersFilters'] = $this->getFilterParams();
        $result['relatedGroups'] = $this->getRelatedGroups();
        $result["childs"] = $this->getChilds();
        $modf = new Modification();
        $result["modificationsGroups"] = $modf->fetch();
        $result["customFields"] = $this->getCustomFields();
        if (empty($result["customFields"])) $result["customFields"] = false;
        return $result;
    }

    private function getCustomFields()
    {
        try {
            $this->createDbUserFields();
            $idGroup = intval($this->input["id"]);
            $u = new DB('shop_userfields', 'su');
            $u->select("cu.id, cu.id_shopgroup, cu.value, su.id id_userfield, 
                     su.name, su.required, su.enabled, su.type, su.placeholder, su.description, su.values, sug.id id_group, sug.name name_group");
            $u->leftJoin('shop_group_userfields cu', "cu.id_userfield = su.id AND cu.id_shopgroup = {$idGroup}");
            $u->leftJoin('shop_userfield_groups sug', 'su.id_group = sug.id');
            $u->where('su.data = "productgroup"');
            $u->groupBy('su.id');
            $u->orderBy('sug.sort');
            $u->addOrderBy('su.sort');
            $result = $u->getList();

            $groups = array();
            foreach ($result as $item) {
                $groups[intval($item["idGroup"])]["id"] = $item["idGroup"];
                $groups[intval($item["idGroup"])]["name"] = empty($item["nameGroup"]) ? "Без категории" : $item["nameGroup"];
                if ($item['type'] == "date")
                    $item['value'] = date('Y-m-d', strtotime($item['value']));
                $groups[intval($item["idGroup"])]["items"][] = $item;
            }
            $grlist = array();
            foreach ($groups as $id => $gr) {
                $grlist[] = $gr;
            }
            return $grlist;
        } catch (Exception $e) {
            return false;
        }
    }

    private function createDbUserFields()
    {
        DB::query("CREATE TABLE IF NOT EXISTS `shop_group_userfields` (
          `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
          `id_shopgroup` int(10) UNSIGNED NOT NULL,
          `id_userfield` int(10) UNSIGNED NOT NULL,
          `value` text,
          `updated_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00' ON UPDATE CURRENT_TIMESTAMP,
          `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          KEY `FK_shop_group_userfields_id_shopgroup` (`id_shopgroup`),
          KEY `FK_shop_group_userfields_sid_userfield` (`id_userfield`),
          CONSTRAINT `shop_group_userfields_ibfk_1` FOREIGN KEY (`id_shopgroup`) REFERENCES `shop_group` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
          CONSTRAINT `shop_group_userfields_ibfk_2` FOREIGN KEY (`id_userfield`) REFERENCES `shop_userfields` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;");
    }

    public function save()
    {
        if (isset($this->input["codeGr"])) {
            $this->input["codeGr"] = strtolower(se_translite_url($this->input["codeGr"]));
        }
        parent::save();
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
                $u->where("id_group IN ($idsStr) AND NOT (id IN (?))", $idsStore);
                $u->deleteList();
            } else {
                $u = new DB('shop_group_img', 'si');
                $u->where('id_group IN (?)', $idsStr);
                $u->deleteList();
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

    private function saveRelatedGroups()
    {
        try {
            $idsGroups = $this->input["ids"];
            $links = $this->input["relatedGroups"];
            $idsExists = array();
            foreach ($links as $group)
                if ($group["id"])
                    $idsExists[] = $group["id"];
            if (CORE_VERSION != "5.3")
                $idsExists = array_diff($idsExists, $idsGroups);
            $idsExistsStr = implode(",", $idsExists);
            $idsStr = implode(",", $idsGroups);

            $u = new DB('shop_group_related');
            $u->where("`id_group` = `id_related`");
            $u->deleteList();

            if ($idsExistsStr) {
                //$u->where("((NOT id_related IN ({$idsExistsStr})) AND id_group IN (?)) OR
                //           ((NOT id_group IN ({$idsExistsStr})) AND id_related IN (?))", $idsStr)->deleteList();

                $u->where("(NOT id_related IN ({$idsExistsStr})) AND id_group IN (?)", $idsStr);
                $u->deleteList();
            } else {
                //else $u->where('id_group IN (?) OR id_acc IN (?)', $idsStr)->deleteList();
                $u->where('id_group IN (?)', $idsStr);
                $u->deleteList();
            }
            $idsExists = array();
            if ($idsExistsStr) {
                $u->select("id_related, id_group");
                $u->where("(id_related IN ({$idsExistsStr})) AND id_group IN (?)", $idsStr);
                $objects = $u->getList();
                foreach ($objects as $item) {
                    //$idsExists[] = $item["idGroup"];
                    $idsExists[] = $item["idRelated"];
                }
            };
            $data = array();
            foreach ($links as $group)
                if (empty($idsExists) || !in_array($group["id"], $idsExists))
                    foreach ($idsGroups as $idGroup) {
                        if ($idGroup !== $group['id'])
                            $data[] = array('id_group' => $idGroup, 'id_related' => $group['id']);
                    }
            if (!empty($data)) {
                DB::insertList('shop_group_related', $data);
            }
        } catch (Exception $e) {
            $this->error = "Не удаётся сохранить похожие категории!";
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

            $u = new DB('shop_crossgroup', 'scg');
            if ($idsExistsStr) {
                $u->where("(NOT group_id IN ({$idsExistsStr})) AND id IN (?)", $idsStr);
                $u->deleteList();
            } else {
                $u->where('id IN (?)', $idsStr);
                $u->deleteList();
            }
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
            $u->where('id_group IN (?)', $idsStr);
            $u->deleteList();

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

    private function saveCustomFields()
    {
        if (!isset($this->input["customFields"]) && !$this->input["customFields"])
            return true;

        try {
            $idCategory = $this->input["id"];
            $groups = $this->input["customFields"];
            $customFields = [];
            foreach ($groups as $group)
                foreach ($group["items"] as $item)
                    $customFields[] = $item;
            foreach ($customFields as $field) {
                $u = new DB('shop_group_userfields', 'cu');
                if (!$field["value"] && $field['id']) {
                    $u->where('id=?', $field['id'])->deleteList();
                } else {
                    $field["idShopgroup"] = $idCategory;
                    $u->setValuesFields($field);
                    $u->save();
                }
            }
            return true;
        } catch (Exception $e) {
            $this->error = "Не удаётся сохранить доп. информацию о товаре!";
            throw new Exception($this->error);
        }
    }


    static public function getUrl($code, $id = null)
    {
        $code_n = $code;
        $id = (int)$id;
        $u = new DB('shop_group', 'sg');
        $i = 1;
        while ($i < 1000) {
            $u->findList("sg.code_gr = '$code_n' AND id <> {$id}");
            $data = $u->fetchOne();
            if ($data["id"])
                $code_n = $code . "-$i";
            else return $code_n;
            $i++;
        }
        return uniqid();
    }

    static public function getLevel($id)
    {
        $level = 0;
        $sqlLevel = 'SELECT `level` FROM shop_group_tree WHERE id_parent = :id_parent AND id_child = :id_parent LIMIT 1';
        $sth = DB::prepare($sqlLevel);
        $params = array("id_parent" => $id);
        $answer = $sth->execute($params);
        if ($answer !== false) {
            $items = $sth->fetchAll(\PDO::FETCH_ASSOC);
            if (count($items))
                $level = $items[0]['level'];
        }
        return $level;
    }

    public function saveIdParent($id, $idParent)
    {
        try {
            $idParent = intval($idParent);
            $u = new DB('shop_group_tree');
            $u->select('id');
            $u->where('id_child = ?', $id);
            if ($idParent) {
                $u->andWhere('id_parent = ?', $idParent);
            } else {
                $u->andWhere('level = 0');
            }
            $answer = $u->fetchOne();
            //if ($idParent) {
            //    DB::query("DELETE FROM shop_group_tree WHERE id_child = {$id} AND id_parent<>{$idParent}");
            //}
            if (empty($answer)) {
               $this->updateGroupTable();
            }
        } catch (Exception $e) {
            throw new Exception("Не удаётся сохранить родителя группы!");
        }
    }

    protected function correctValuesBeforeSave()
    {
        if (!$this->input["id"] && !$this->input["ids"] || isset($this->input["codeGr"])) {
            if (empty($this->input["codeGr"])) {
                $this->input["codeGr"] = strtolower(se_translite_url($this->input["name"]));
                if (empty($this->input["codeGr"])) $this->input["codeGr"] = 'category' . time();
            }
            $this->input["codeGr"] = $this->getUrl($this->input["codeGr"], $this->input["id"]);
        }
        if (isset($this->input["idModificationGroupDef"]) && empty($this->input["idModificationGroupDef"]))
            $this->input["idModificationGroupDef"] = null;
        if (isset($this->input["active"]) && is_bool($this->input["active"]))
            $this->input["active"] = $this->input["active"] ? "Y" : "N";
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
        $this->saveRelatedGroups();
        if (CORE_VERSION == "5.3") {
            //$tgroup = new Category($this->input);
            //$group = $tgroup->info();
            $this->saveIdParent($this->input["id"], $this->input["upid"]);
        }
        $this->saveCustomFields();
        return true;
    }

    // Обновляем структуру баз
    public function updateGroupTable() {
        $sql = "CREATE TABLE IF NOT EXISTS shop_group_tree (
            id int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
            id_parent int(10) UNSIGNED NOT NULL,
            id_child int(10) UNSIGNED NOT NULL,
            level tinyint(4) NOT NULL,
            updated_at timestamp NOT NULL DEFAULT '0000-00-00 00:00:00' ON UPDATE CURRENT_TIMESTAMP,
            created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE INDEX UK_shop_group_tree (id_parent, id_child),
            CONSTRAINT FK_shop_group_tree_shop_group_id FOREIGN KEY (id_child)
            REFERENCES shop_group (id) ON DELETE CASCADE ON UPDATE CASCADE,
            CONSTRAINT FK_shop_group_tree_shop_group_tree_id_parent FOREIGN KEY (id_parent)
            REFERENCES shop_group (id) ON DELETE CASCADE ON UPDATE RESTRICT
            )
            ENGINE = INNODB
            CHARACTER SET utf8
            COLLATE utf8_general_ci;";

        DB::query($sql);

        $this->treepath = array();
        $tree = array();

        $tbl = new DB('shop_group', 'sg');
        $tbl->select('upid, id');
        $list = $tbl->getList();
        foreach($list as $it){
            $tree[intval($it['upid'])][] = $it['id'];
        }


        unset($list);
        $data = $this->addInTree($tree);
        DB::query("TRUNCATE TABLE `shop_group_tree`");
        DB::insertList('shop_group_tree', $data);

    }

    private function addInTree($tree , $parent = 0, $level = 0){
        if ($level == 0) {
            $this->treepath = array();
        } else
            $this->treepath[$level] = $parent;

        foreach($tree[$parent] as $id) {
            $data[] = array('id_parent'=>$id, 'id_child'=>$id, 'level'=>$level);
            if ($level > 0)
                for ($l=1; $l <= $level; $l++){
                    $data[] = array('id_parent'=>$this->treepath[$l], 'id_child'=>$id, 'level'=>$level);
                }
            if (!empty($tree[$id])) {
                $data = array_merge ($data, $this->addInTree($tree , $id, $level + 1));
            }
        }
        return $data;
    }

}