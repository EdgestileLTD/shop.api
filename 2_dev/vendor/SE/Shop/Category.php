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

    // получить родительский пункт
    private function getParentItem($item, $items)
    {
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');
        foreach ($items as $it)
            if ($it["id"] == $item["upid"])
                return $it;
    }

    // получить имя пути
    private function getPathName($item, $items)
    {
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');
        if (!$item["upid"])
            return $item["name"];

        $parent = $this->getParentItem($item, $items);
        if (!$parent)
            return $item["name"];
        return $this->getPathName($parent, $items) . " / " . $item["name"];
    }

    // получить патчи
    public function getPatches($items)
    {
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');
        $result = array();
        $search = strtolower($this->input["searchText"]);
        foreach ($items as $item) {
            $searchName = mb_strtolower($item["name"]);
            if (empty($search) || mb_strpos($searchName, mb_strtolower($search)) !== false) {
                $item["name"] = $this->getPathName($item, $items);
                $item["level"] = substr_count($item["name"], "/");
                $result[] = $item;
            }
            unset($searchName);
        }
        return $result;
    }

    // просмотреть полученную структуру
    private function getTreeView($items, $idParent = null)
    {
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');
        $result = array();
        foreach ($items as $item) {
            if ($item["upid"] == $idParent) {
                $item["childs"] = $this->getTreeView($items, $item["id"]);
                $result[] = $item;
            }
        }
        return $result;
    }

    // установить id основного родителя
    private function setIdMainParent($items)
    {
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');
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

    // получить настройки
    protected function getSettingsFetch()
    {
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');
        if (CORE_VERSION != "5.2") {
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

    // получить информацию по настройкам
    protected function getSettingsInfo()
    {
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');
        return $this->getSettingsFetch();
    }

    // информация
    public function info($id = null)
    {
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');
        $result = parent::info();

        // получаем список похожих категорий
        $u = new DB('shop_group', 'sg');
        $u->select('sg.name, sg.position, sg.id');
        $u->innerJoin("(
            SELECT id_related AS id
            FROM shop_group_related
            WHERE id_group = {$result['id']}
            AND type = 1
            UNION
            SELECT id_group AS id
            FROM shop_group_related
            WHERE id_related = {$result['id']}
            AND type = 1
            AND is_cross
        ) sgr", 'sg.id = sgr.id');
        $u->orderBy('sg.upid');
        $result['similar'] = $u->getList();
        unset($u);

        if (CORE_VERSION != "5.2") {
            $arr = $this->setIdMainParent(array($result));
            $this->result = $arr[0];
        }
        $this->result["nameParent"] = $this->getNameParent();
        return $this->result;
    }

    // получить правильные значения
    protected function correctItemsBeforeFetch($items = array())
    {
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');
        if (CORE_VERSION != "5.2")
            $items = $this->setIdMainParent($items);
        if ($this->input["isTree"] && empty($this->input["searchText"]))
            $result = $this->getTreeView($items);
        else $result = $this->getPatches($items);
        return $result;
    }

    // получить скидки
    public function getDiscounts($idCategory = null)
    {
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');
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

    // получить изображения
    public function getImages($idCategory = null)
    {
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');
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

    // получить поставки
    public function getDeliveries($idCategory = null)
    {
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');
        $result = array();
        $id = $idCategory ? $idCategory : $this->input["id"];
        if (!$id)
            return $result;

        return $result;
    }

    // получить ссылки групп
    public function getLinksGroups($idCategory = null)
    {
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');
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

    // перевод
    private function translate($name)
    {
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');
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

    // получить отфильтрованные параметры
    public function getFilterParams($idCategory = null)
    {
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');
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


    // получить детей
    protected function getChilds()
    {
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');
        $idParent = $this->input["id"];
        if (CORE_VERSION != "5.2") {
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

    // получить имя родителя
    private function getNameParent()
    {
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');
        if (!$this->result["upid"])
            return null;

        $db = new DB("shop_group");
        $db->select("name");
        $result = $db->getInfo($this->result["upid"]);
        return $result["name"];
    }

    // добавить полученную информацию
    protected function getAddInfo()
    {
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');
        $result["discounts"] = $this->getDiscounts();
        $result["images"] = $this->getImages();
        $result["deliveries"] = $this->getDeliveries();
        $result['linksGroups'] = $this->getLinksGroups();
        $result['parametersFilters'] = $this->getFilterParams();
        $result["childs"] = $this->getChilds();
        $modf = new Modification();
        $result["modificationsGroups"] = $modf->fetch();
        $result["customFields"] = $this->getCustomFields();
        if (empty($result["customFields"])) $result["customFields"] = false;
        return $result;
    }

    // получить пользовательские поля
    private function getCustomFields()
    {
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');
        try {
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

    // сохранить
    public function save()
    {
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');

        // получаем список похожих категорий
        $u = new DB('shop_group', 'sg');
        $u->select('sg.name, sg.position, sg.id');
        $u->innerJoin("(
            SELECT id_related AS id
            FROM shop_group_related
            WHERE id_group = {$this->input["id"]}
            AND type = 1
            UNION
            SELECT id_group AS id
            FROM shop_group_related
            WHERE id_related = {$this->input["id"]}
            AND type = 1
            AND is_cross
        ) sgr", 'sg.id = sgr.id');
        $u->orderBy('sg.upid');
        $similarOld = $u->getList();
        unset($u);
        // выявляем удаленные связи через сверку
        foreach ($similarOld as $keyOld => $valueOld)
            $similarOld[$keyOld]['delete'] = true;
        foreach ($similarOld as $keyOld => $valueOld)
            foreach ($this->input["similar"] as $keyN => $valueN)
                if ($valueOld['id'] == $valueN['id'])
                    $similarOld[$keyOld]['delete'] = false;
        // по сформированносу временному масиву $similarOld удаляем из БД похожие к.
        foreach ($similarOld as $keyOld => $valueOld) {
            if ($valueOld['delete'] == true) {
                DB::query("DELETE FROM shop_group_related WHERE id_group = {$valueOld['id']} AND id_related = {$this->input["id"]}");
                DB::query("DELETE FROM shop_group_related WHERE id_group = {$this->input["id"]} AND id_related = {$valueOld['id']}");
            }
        }
        unset($similarOld);

        if (isset($this->input["codeGr"])) {
            $this->input["codeGr"] = strtolower(se_translite_url($this->input["codeGr"]));
        }
        parent::save();
    }


    // сохранить скидки
    private function saveDiscounts()
    {
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');
        try {
            foreach ($this->input["ids"] as $id)
                DB::saveManyToMany($id, $this->input["discounts"],
                    array("table" => "shop_discount_links", "key" => "id_group", "link" => "discount_id"));
        } catch (Exception $e) {
            $this->error = "Не удаётся сохранить скидки категории товара!";
            throw new Exception($this->error);
        }
    }

    // сохранить изображения
    private function saveImages()
    {
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');
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

    // сохранить ссылки групп
    private function saveLinksGroups()
    {
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');
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

    // сохранить параметры фильтров
    private function saveParametersFilters()
    {
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');
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

    // сохранить пользовательские поля
    private function saveCustomFields()
    {
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');
        if (!isset($this->input["customFields"]) && !$this->input["customFields"])
            return true;

        try {
            $idCategory = $this->input["id"];
            $groups = $this->input["customFields"];
            $customFields = array();
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


    // получить url
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

    // получить уровень
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

    // сохранить id родителя
    public function saveIdParent($id, $idParent)
    {
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');
        try {
            $levelIdOld = self::getLevel($id);
            $level = 0;
            DB::query("DELETE FROM shop_group_tree WHERE id_child = {$id}");

            $sqlGroupTree = "INSERT INTO shop_group_tree (id_parent, id_child, `level`)
                                SELECT id_parent, :id, :level FROM shop_group_tree
                                WHERE id_child = :id_parent
                                UNION ALL
                                SELECT :id, :id, :level";
            $sthGroupTree = DB::prepare($sqlGroupTree);
            if (!empty($idParent)) {
                $level = self::getLevel($idParent);
                $level++;
            }
            $sthGroupTree->execute(array('id_parent' => $idParent, 'id' => $id, 'level' => $level));
            $levelIdNew = self::getLevel($id);
            $diffLevel = $levelIdNew - $levelIdOld;
            DB::query("UPDATE shop_group_tree SET `level` = `level` + {$diffLevel}  WHERE id_parent = {$id} AND id_child <> {$id}");
        } catch (Exception $e) {
            throw new Exception("Не удаётся сохранить родителя группы!");
        }
    }

    // сохранить id похожей категории
    public function saveIdSimilar($id, $idRelated)
    {
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');
        try {
            DB::query("DELETE FROM shop_group_related WHERE id_group = {$id} AND id_related = {$idRelated}");
            DB::query("DELETE FROM shop_group_related WHERE id_group = {$idRelated} AND id_related = {$id}");
            $sqlGroupRelated = "INSERT INTO shop_group_related (id_group, id_related)
                                SELECT id_group, :id_related FROM shop_group_related
                                UNION
                                SELECT :id_group, :id_related";
            $sthGroupTree = DB::prepare($sqlGroupRelated);
            $sthGroupTree->execute(array('id_group' => $id, 'id_related' => $idRelated));
            unset($sqlGroupRelated);
        } catch (Exception $e) {
            throw new Exception("Не удаётся сохранить похожие категории!");
        }
    }

    // правильные значения перед сохранением
    protected function correctValuesBeforeSave()
    {
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');
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

    // сохранить добавленную информацию
    protected function saveAddInfo()
    {
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');
        $this->input["ids"] = empty($this->input["ids"]) ? array($this->input["id"]) : $this->input["ids"];
        if (!$this->input["ids"])
            return false;

        $this->saveDiscounts();
        $this->saveImages();
        $this->saveLinksGroups();
        $this->saveParametersFilters();
        // если присутствуют похожие - запускаем метод записи
        if(!empty($this->input["similar"]))
            foreach ($this->input["similar"] as $num => $similar)
                $this->saveIdSimilar($this->input["id"], $similar['id']);
        $this->saveIdParent($this->input["id"], $this->input["upid"]);
        $this->saveCustomFields();
        return true;
    }

}