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
        if ($_SESSION['coreVersion'] > 520) {
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
        $u->innerJoin("shop_group_related sgr", "sgr.id_related = sg.id");
        $u->orderBy('sg.upid');
        $u->addOrderBy("sg.position");
        $u->where("sgr.id_group = ?", $this->input["id"]);
        $result['similar'] = $u->getList();
        unset($u);

        if ($_SESSION['coreVersion'] > 520) {
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
        if ($_SESSION['coreVersion'] > 520)
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
        if ($_SESSION['coreVersion'] > 520) {
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
    public function save($isTransactionMode = true)
    {
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');

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
            if ($_SESSION['coreVersion'] < 530)
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
                if (!(int)$filter["id"]) {
                    $filter["id"] = null;
                }
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

        /** Сохранение связей таблиц в shop_group_tree
         *  1 получаем ids всех детей группы
         *  2 выравниваем массив, заменяем данные на новые
         *  3 циклом сохраняем данные для группы и ее детей в shop_group_tree
         */
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');


        /** 1 получаем ids всех детей группы */
        $u = new DB('shop_group_tree', 'sgt');
        $u->select('sgt.id_child, sg.upid');
        $u->leftjoin('shop_group sg', 'sg.id = sgt.id_child');
        $u->where("id_parent = '?'", $id);
        $u->orderBy('sgt.id_child');
        $u->groupBy('sgt.id_child');
        $idsDB = $u->getList();
        unset($u);


        /** 2 выравниваем массив, заменяем данные на новые */
        $ids = array();
        foreach ($idsDB as $k => $i)
            $ids[$i['idChild']] = $i['upid'];
        $ids[$id] = $idParent;
        unset($idsDB);unset($id);unset($idParent);


        /** 3 циклом сохраняем данные для группы и ее детей в shop_group_tree */
        foreach ($ids as $id => $idParent) {
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

        $this->saveSimilar();
        $this->saveDiscounts();
        $this->saveImages();
        $this->saveLinksGroups();
        $this->saveParametersFilters();
        $this->saveIdParent($this->input["id"], $this->input["upid"]);
        $this->saveCustomFields();
        return true;
    }


    public function delete()
    {
        $result = parent::delete();
        if ($result && ($_SESSION['coreVersion'] >= 530))
            DB::query('DELETE FROM shop_group WHERE NOT id IN (SELECT t.id_parent FROM shop_group_tree t)');

        return $result;
    }

    private function saveSimilar()
    {
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');

        if (!isset($this->input["similar"]))
            return true;


        try {
            $idsExists = [];
            $idsStr = implode(",", $this->input["ids"]);
            $similar = $this->input["similar"];
            foreach ($similar as $similarItem)
                $idsExists[] = $similarItem["id"];
            $idsExistsStr = implode(",", $idsExists);
            $u = new DB("shop_group_related", "sgr");
            if ($idsExistsStr) {
                $u->where("(NOT id_related IN ({$idsExistsStr})) AND id_group IN (?)", $idsStr);
                $u->deleteList();
            } else {
                $u->where('id_group IN (?)', $idsStr);
                $u->deleteList();
            }
            $data = [];
            foreach ($this->input["ids"] as $idGroup) {
                $u = new DB("shop_group_related", "sgr");
                $u->select("sgr.id_related");
                $u->where("sgr.id_group = ?", $idGroup);
                $result = $u->getList();
                $idsExists = [];
                foreach ($result as $related)
                    $idsExists[] = $related["idRelated"];
                foreach ($similar as $similarItem) {
                    if (!in_array($similarItem["id"], $idsExists)) {
                        if ($idGroup != $similarItem["id"])
                            $data[] = ["id_group" => $idGroup, "id_related" => $similarItem["id"], "is_cross" => 0, "type" => 1];
                    }

                }
            }

            if (!empty($data)) {
                DB::insertList('shop_group_related', $data, true);
            }


        } catch (Exception $e) {
            $this->error = "Не удаётся сохранить похожие категории!";
            throw new Exception($this->error);
        }
    }

}