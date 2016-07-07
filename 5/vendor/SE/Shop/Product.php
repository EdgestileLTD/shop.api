<?php

namespace SE\Shop;

use SE\DB;

class Product extends Base
{
    protected $tableName = "shop_price";

    protected function getSettingsFetch()
    {
        $result["select"] = 'sp.*, sg.name name_group, sb.name name_brand';
        if (CORE_VERSION == "5.3") {
            $joins[] = array(
                "type" => "left",
                "table" => 'shop_price_group spg',
                "condition" => 'spg.id_price = sp.id'
            );
            $joins[] = array(
                "type" => "left",
                "table" => 'shop_group sg',
                "condition" => 'sg.id = spg.id_group'
            );
        } else
            $joins[] = array(
                "type" => "left",
                "table" => 'shop_group sg',
                "condition" => 'sg.id = sp.id_group'
            );
        $joins[] = array(
            "type" => "left",
            "table" => 'shop_brand sb',
            "condition" => 'sb.id = sp.id_brand'
        );
        $joins[] = array(
            "type" => "left",
            "table" => 'shop_group_price sgp',
            "condition" => 'sp.id = sgp.price_id'
        );
        $result["joins"] = $joins;
        return $result;
    }

    protected function getSettingsInfo()
    {
        return $this->getSettingsFetch();
    }

    public function getImages($idProduct = null)
    {
        $result = array();
        $id = $idProduct ? $idProduct : $this->input["id"];
        if (!$id)
            return $result;

        $u = new DB('shop_img', 'si');
        $u->where('si.id_price = ?', $id);
        $u->orderBy("sort");
        $objects = $u->getList();

        foreach ($objects as $item) {
            $image = null;
            $image['id'] = $item['id'];
            $image['imageFile'] = $item['picture'];
            $image['imageAlt'] = $item['pictureAlt'];
            $image['sortIndex'] = $item['sort'];
            $image['isMain'] = (bool)$item['default'];
            if ($image['imageFile']) {
                if (strpos($image['imageFile'], "://") === false) {
                    $image['imageUrl'] = 'http://' . HOSTNAME . "/images/rus/shopprice/" . $image['imageFile'];
                    $image['imageUrlPreview'] = "http://" . HOSTNAME . "/lib/image.php?size=64&img=images/rus/shopprice/" . $image['imageFile'];
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

    public function getSpecifications($idProduct = null)
    {
        $result = array();
        $id = $idProduct ? $idProduct : $this->input["id"];
        if (!$id)
            return $result;

        $u = new DB('shop_modifications_feature', 'smf');
        $u->select('IFNULL(sfg.id, 0) id_group, sfg.name group_name, sf.name name_feature,
                    sf.type, sf.measure, smf.*, sfvl.value, sfvl.color, sfg.sort index_group');
        $u->innerJoin('shop_feature sf', 'sf.id = smf.id_feature');
        $u->leftJoin('shop_feature_value_list sfvl', 'smf.id_value = sfvl.id');
        $u->leftJoin('shop_feature_group sfg', 'sfg.id = sf.id_feature_group');
        $u->where('smf.id_price=? AND smf.id_modification IS NULL', $id);
        $u->orderBy('sfg.sort');
        $u->addOrderBy('sf.sort');

        $newTypes = array("string" => "S", "number" => "D", "bool" => "B", "list" => "L", "colorlist" => "CL");

        $objects = $u->getList();
        foreach ($objects as $item) {
            $specification = null;
            $specification['id'] = $item['id'];
            $specification['name'] = $item['nameFeature'];
            $specification['idGroup'] = $item['idGroup'];
            $specification['nameGroup'] = $item['groupName'];
            $specification['idFeature'] = $item['idFeature'];
            $specification['type'] = $item['type'];
            $specification['valueType'] = $newTypes[$item['type']];
            $specification['measure'] = $item['measure'];
            $specification['valueIdList'] = $item['idValue'];
            $specification['idValue'] = $item['idValue'];
            $specification['valueList'] = $item['value'];
            $specification['valueNumber'] = (float)$item['valueNumber'];
            $specification['valueBool'] = (bool)$item['valueBool'];
            $specification['valueString'] = $item['valueString'];
            switch ($specification['valueType']) {
                case "S":
                    $specification["value"] = $item['valueString'];
                    break;
                case "D":
                    $specification["value"] = $item['valueNumber'];
                    break;
                case "B":
                    $specification["value"] = $item['valueBool'];
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
            $specification['sortIndexGroup'] = $item['indexGroup'];
            $result[] = $specification;
        }
        return $result;
    }

    public function getSimilarProducts($idProduct = null)
    {
        $result = array();
        $id = $idProduct ? $idProduct : $this->input["id"];
        if (!$id)
            return $result;

        $u = new DB('shop_sameprice', 'ss');
        $u->select('sp1.id id1, sp1.name name1, sp1.code code1, sp1.article article1, sp1.price price1,
                    sp2.id id2, sp2.name name2, sp2.code code2, sp2.article article2, sp2.price price2');
        $u->innerJoin('shop_price sp1', 'sp1.id = ss.id_price');
        $u->innerJoin('shop_price sp2', 'sp2.id = ss.id_acc');
        $u->where('sp1.id = ? OR sp2.id = ?', $id);
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
            $result[] = $similar;
        }
        return $result;
    }

    public function getAccompanyingProducts($idProduct = null)
    {
        $result = array();
        $id = $idProduct ? $idProduct : $this->input["id"];
        if (!$id)
            return $result;

        $u = new DB('shop_accomp', 'sa');
        $u->select('sp.id, sp.name, sp.code, sp.article, sp.price');
        $u->innerJoin('shop_price sp', 'sp.id = sa.id_acc');
        $u->where('sa.id_price = ?', $id);
        $objects = $u->getList();
        foreach ($objects as $item) {
            $accompanying = null;
            $accompanying['id'] = $item['id'];
            $accompanying['name'] = $item['name'];
            $accompanying['code'] = $item['code'];
            $accompanying['article'] = $item['article'];
            $accompanying['price'] = (real)$item['price'];
            $result[] = $accompanying;
        }
        return $result;
    }
    
    public function getComments($idProduct = null)
    {
        $id = $idProduct ? $idProduct : $this->input["id"];
        $comment = new Comment();
        return $comment->fetchByIdProduct($id);
    }

    public function getCrossGroups($idProduct = null)
    {
        $result = array();
        $id = $idProduct ? $idProduct : $this->input["id"];
        if (!$id)
            return $result;

        if (CORE_VERSION == "5.3") {
            $u = new DB('shop_price_group', 'spg');
            $u->select('sg.id, sg.name');
            $u->innerJoin('shop_group sg', 'sg.id = spg.id_group');
            $u->where('spg.id_price = ? AND NOT spg.is_main', $id);
        } else {
            $u = new DB('shop_group_price', 'sgp');
            $u->select('sg.id, sg.name');
            $u->innerJoin('shop_group sg', 'sg.id = sgp.group_id');
            $u->where('sgp.price_id = ?', $id);
        }
        return $u->getList();
    }

    public function fillModifications(&$product)
    {
        $id = $product["id"];
        if (!$id)
            return;

        $newTypes = array("string" => "S", "number" => "D", "bool" => "B", "list" => "L", "colorlist" => "CL");

        $u = new DB('shop_modifications', 'sm');
        $u->select('smg.*,
                GROUP_CONCAT(DISTINCT(CONCAT_WS("\t", sf.id, sf.name, sf.`type`, sf.sort)) SEPARATOR "\n") `columns`');
        $u->innerJoin('shop_modifications_group smg', 'smg.id = sm.id_mod_group');
        $u->innerJoin('shop_modifications_feature smf', 'smf.id_modification = sm.id');
        $u->innerJoin('shop_feature sf', 'sf.id = smf.id_feature');
        $u->where('sm.id_price = ?', $id);
        $u->groupBy('smg.id');
        $u->orderBy('smg.sort');
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

        $u = new DB('shop_modifications', 'sm');
        $u->select('sm.*,
                SUBSTRING(GROUP_CONCAT(DISTINCT(CONCAT_WS("\t", sfvl.id_feature, sfvl.id, sfvl.value, sfvl.sort, sfvl.color)) SEPARATOR "\n"), 1) values_feature,
                SUBSTRING(GROUP_CONCAT(DISTINCT(CONCAT_WS("\t", smi.id_img, smi.sort, si.picture)) SEPARATOR "\n"), 1) images');
        $u->innerJoin('shop_modifications_feature smf', 'sm.id = smf.id_modification');
        $u->innerJoin('shop_feature_value_list sfvl', 'sfvl.id = smf.id_value');
        $u->leftJoin('shop_modifications_img smi', 'sm.id = smi.id_modification');
        $u->leftJoin('shop_img si', 'smi.id_img = si.id');
        $u->where('sm.id_price = ?', $id);
        $u->groupBy();
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
                $modification['priceSmallOpt'] = (real)$item['valueOpt'];
                $modification['priceOpt'] = (real)$item['valueOptCorp'];
                $modification['description'] = $item['description'];
                $features = explode("\n", $item['valuesFeature']);
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
                                $value['imageUrl'] = 'http://' . HOSTNAME . "/images/rus/shopprice/" . $value['imageFile'];
                                $value['imageUrlPreview'] = "http://" . HOSTNAME . "/lib/image.php?size=64&img=images/{$lang}/shopprice/" . $value['imageFile'];
                            } else {
                                $value['imageUrl'] = $image['imageFile'];
                                $value['imageUrlPreview'] = $image['imageFile'];
                            }
                        }
                        $modification['images'][] = $value;
                    }
                }
                foreach ($groups as &$group) {
                    if ($group['id'] == $item['idModGroup']) {
                        $group['items'][] = $modification;
                    }
                }
                $existFeatures[] = $item['valuesFeature'];
            }
        }
        $product['modifications'] = $groups;
        $product['countModifications'] = count($objects);
    }

    protected function getAddInfo()
    {
        $result["images"] = $this->getImages();
        $result["specifications"] = $this->getSpecifications();
        $result["similarProducts"] = $this->getSimilarProducts();
        $result["accompanyingProducts"] = $this->getAccompanyingProducts();
        $result["comments"] = $this->getComments();
        $result["crossGroups"] = $this->getCrossGroups();
        $this->fillModifications($this->result);
        return $result;
    }

    private function getUrl($code)
    {
        $code_n = $code;
        $u = new DB('shop_price', 'sp');
        $i = 1;
        while ($i < 1000) {
            $data = $u->findList("sp.code='$code_n'")->fetchOne();
            if ($data["id"])
                $code_n = $code . "-$i";
            else return $code_n;
            $i++;
        }
        return uniqid();
    }

    protected function correctValuesBeforeSave()
    {
        if (!$this->input["id"] || isset($this->input["code"])) {
            if (empty($this->input["code"]))
                $this->input["code"] = strtolower(se_translite_url($this->input["name"]));
            $this->input["code"] = $this->getUrl($this->input["code"]);
        }
    }

    protected function saveAddInfo()
    {
        
        return true;
    }


}
