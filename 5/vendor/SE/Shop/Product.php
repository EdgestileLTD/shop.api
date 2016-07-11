<?php

namespace SE\Shop;

use SE\DB;
use SE\Exception;

class Product extends Base
{
	protected $tableName = "shop_price";
	private $newImages;

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

		try {
			$u = new DB('shop_modifications_feature', 'smf');
			$u->select('sfg.id id_group, sfg.name group_name, sf.name,
						sf.type, sf.measure, smf.*, sfvl.value, sfvl.color, sfg.sort index_group');
			$u->innerJoin('shop_feature sf', 'sf.id = smf.id_feature');
			$u->leftJoin('shop_feature_value_list sfvl', 'smf.id_value = sfvl.id');
			$u->leftJoin('shop_feature_group sfg', 'sfg.id = sf.id_feature_group');
			$u->where('smf.id_price = ? AND smf.id_modification IS NULL', $id);
			$u->orderBy('sfg.sort');
			$u->addOrderBy('sf.sort');
			$items = $u->getList();
			$result = array();
			foreach ($items as $item) {
				if ($item["type"] == "number")
					$item["value"] = (real) $item["valueNumber"];
				elseif ($item["type"] == "string")
					$item["value"] = $item["valueString"];
				elseif ($item["type"] == "bool")
					$item["value"] = (bool) $item["valueBool"];
				$result[] = $item;
			}
			return $result;
		} catch (Exception $e) {
			$this->error = "Не удаётся получить характеристики товара!";
		}
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

	public function getModifications($idProduct = null)
	{
		$result = array();
		$id = $idProduct ? $idProduct : $this->input["id"];
		if (!$id)
			return $result;

		$newTypes = array("string" => "S", "number" => "D", "bool" => "B", "list" => "L", "colorlist" => "CL");
		$product = array();

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
			return $result;

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
					$value['value'] = $feature[2];
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
								$value['imageUrlPreview'] = "http://" . HOSTNAME . "/lib/image.php?size=64&img=images/rus/shopprice/" . $value['imageFile'];
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
		return $groups;
	}

    public function getDiscounts($idProduct = null)
    {
        $result = array();
        $id = $idProduct ? $idProduct : $this->input["id"];
        if (!$id)
            return $result;

        $u = new DB('shop_discounts', 'sd');
        $u->select('sd.*');
        $u->innerJoin('shop_discount_links sdl', 'sdl.discount_id = sd.id');
        $u->where('sdl.id_price = ?', $id);
        $u->orderBy('sd.id');
        return $u->getList();
    }

	protected function getAddInfo()
	{
		$result["images"] = $this->getImages();
		$result["specifications"] = $this->getSpecifications();
		$result["similarProducts"] = $this->getSimilarProducts();
		$result["accompanyingProducts"] = $this->getAccompanyingProducts();
		$result["comments"] = $this->getComments();
        $result["discounts"] = $this->getDiscounts();
		$result["crossGroups"] = $this->getCrossGroups();
		$result["modifications"] = $this->getModifications();
		return $result;
	}

	private function getUrl($code, $id)
	{
		$code_n = $code;
		$id = (int)$id;
		$u = new DB('shop_price', 'sp');
		$i = 1;
		while ($i < 1000) {
			$data = $u->findList("sp.code = '$code_n' AND id <> {$id}")->fetchOne();
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
			$this->input["code"] = $this->getUrl($this->input["code"], $this->input["id"]);
		}
	}

	private function saveImages()
	{
		try {
            $idsProducts = $this->input["ids"];
            $images = $this->input["images"];
            // обновление изображений
            $idsStore = "";
            foreach ($images as $image) {
                if ($image["id"] > 0) {
                    if (!empty($idsStore))
                        $idsStore .= ",";
                    $idsStore .= $image["id"];
                    $u = new DB('shop_img', 'si');
                    $image["picture"] = $image["imageFile"];
                    $image["sort"] = $image["sortIndex"];
                    $image["pictureAlt"] = $image["imageAlt"];
                    $image["default"] = $image["isMain"];
                    $u->setValuesFields($image);
                    $u->save();
                }
            }

            $idsStr = implode(",", $idsProducts);
            if (!empty($idsStore)) {
                $u = new DB('shop_img', 'si');
                $u->where("id_price IN ($idsStr) AND NOT (id IN (?))", $idsStore)->deleteList();
            } else {
                $u = new DB('shop_img', 'si');
                $u->where('id_price IN (?)', $idsStr)->deleteList();
            }

            $data = array();
            foreach ($images as $image)
                if (empty($image["id"]) || ($image["id"] <= 0)) {
                    foreach ($idsProducts as $idProduct) {
                        $data[] = array('id_price' => $idProduct, 'picture' => $image["imageFile"],
                            'sort' => (int)$image["sortIndex"], 'picture_alt' => $image["imageAlt"],
                            'default' => (int)$image["isMain"]);
                        $newImages[] = $image["imageFile"];
                    }
                }

            if (!empty($data))
                DB::insertList('shop_img', $data);
        } catch (Exception $e) {
            $this->error = "Не удаётся сохранить изображения товара!";
            throw new Exception($this->error);
        }            
	}

	private function getIdSpecificationGroup($name)
	{
		if (empty($name))
			return null;

		$u = new DB('shop_feature_group');
		$u->select('id');
		$u->where('name = "?"', $name);
		$result = $u->fetchOne();
		if (!empty($result["id"]))
			return $result["id"];

		$u = new DB('shop_feature_group');
		$u->setValuesFields(array("name" => $name));
		return $u->save();
	}

	private function getIdFeature($idGroup, $name)
	{
		$u = new DB('shop_feature', 'sf');
		$u->select('id');
		$u->where('name = "?"', $name);
		if ($idGroup)
			$u->andWhere('id_feature_group = ?', $idGroup);
		else $u->andWhere('id_feature_group IS NULL');
		$result = $u->fetchOne();
		if (!empty($result["id"]))
			return $result["id"];

		$u = new DB('shop_feature', 'sf');
		$data = array();
		if ($idGroup)
			$data["idFeatureGroup"] = $idGroup;
		$data["name"] = $name;
		return $u->save();
	}

	public function getSpecificationByName($specification)
	{
		$idGroup = $this->getIdSpecificationGroup($specification->nameGroup);
		$specification->idFeature = $this->getIdFeature($idGroup, $specification->name);
		return $specification;
	}

	private function saveSpecifications()
	{
		try {
            $idsProducts = $this->input["ids"];
			$isAddSpecifications = $this->input["isAddSpecifications"];
			$specifications = $this->input["specifications"];
			$idsStr = implode(",", $idsProducts);
			if (!$isAddSpecifications) {
				$u = new DB('shop_modifications_feature', 'smf');
				$u->where('id_modification IS NULL AND id_price IN (?)', $idsStr)->deleteList();
			}

			$m = new DB('shop_modifications_feature', 'smf');
			$m->select('id');			
			foreach ($specifications as $specification) {				
				foreach ($idsProducts as $idProduct) {
					if ($isAddSpecifications) {
						if (is_string($specification["valueString"]))
							$m->where("id_price = {$idProduct} AND id_feature = {$specification["idFeature"]} AND 
							           value_string = '{$specification["valueString"]}'");
						if (is_bool($specification["valueBool"]))
							$m->where("id_price = {$idProduct} AND id_feature = {$specification["idFeature"]} AND 
							           value_bool = '{$specification["valueBool"]}'");
						if (is_numeric($specification["valueNumber"]))
							$m->where("id_price = {$idProduct} AND id_feature = {$specification["idFeature"]} AND 
							           value_number = '{$specification["valueNumber"]}'");
						if (is_numeric($specification["idValue"]))
							$m->where("id_price = {$idProduct} AND id_feature = {$specification["idFeature"]} AND 
									   id_value = {$specification["idValue"]}");
						$result = $m->fetchOne();
						if ($result["id"])
							continue;
					}
					if ($specification["type"] == "number")
						$specification["valueNumber"] = $specification["value"];
					elseif ($specification["type"] == "string")
						$specification["valueString"] = $specification["value"];
					elseif ($specification["type"] == "bool")
						$specification["valueBool"] = $specification["value"];
					elseif (empty($specification["idValue"]))
						continue;
					$data[] = array('id_price' => $idProduct, 'id_feature' => $specification["idFeature"],
						'id_value' => $specification["idValue"],
						'value_number' => $specification["valueNumber"],
						'value_bool' => $specification["valueBool"], 'value_string' => $specification["valueString"]);
				}
			}			
			if (!empty($data)) 				
				DB::insertList('shop_modifications_feature', $data);			
		} catch (Exception $e) {
			$this->error = "Не удаётся сохранить спецификации товара!";
			throw new Exception($this->error);
		}
	}

    private function saveSimilarProducts()
    {
        try {
            $idsProducts = $this->input["ids"];
            $products = $this->input["similarProducts"];
            $idsExists = array();
            foreach ($products as $p)
                if ($p["id"])
                    $idsExists[] = $p["id"];
            $idsExists = array_diff($idsExists, $idsProducts);
            $idsExistsStr = implode(",", $idsExists);
            $idsStr = implode(",", $idsProducts);
            $u = new DB('shop_sameprice', 'ss');
            if ($idsExistsStr)
                $u->where("((NOT id_acc IN ({$idsExistsStr})) AND id_price IN (?)) OR 
                           ((NOT id_price IN ({$idsExistsStr})) AND id_acc IN (?))", $idsStr)->deleteList();
            else $u->where('id_price IN (?) OR id_acc IN (?)', $idsStr)->deleteList();
            $idsExists = array();
            if ($idsExistsStr) {
                $u->select("id_price, id_acc");
                $u->where("((id_acc IN ({$idsExistsStr})) AND id_price IN (?)) OR 
                            ((id_price IN ({$idsExistsStr})) AND id_acc IN (?))", $idsStr);
                $objects = $u->getList();
                foreach ($objects as $item) {
                    $idsExists[] = $item["idAcc"];
                    $idsExists[] = $item["idPrice"];
                }
            };
            $data = array();
            foreach ($products as $p)
                if (empty($idsExists) || !in_array($p["id"], $idsExists))
                    foreach ($idsProducts as $idProduct)
                        $data[] = array('id_price' => $idProduct, 'id_acc' => $p["id"]);
            if (!empty($data))
                DB::insertList('shop_sameprice', $data);
        } catch (Exception $e) {
            $this->error = "Не удаётся сохранить похожие товары!";
            throw new Exception($this->error);
        }
    }

    private function saveAccompanyingProducts()
    {
        try {
            foreach ($this->input["ids"] as $id)
                DB::saveManyToMany($id, $this->input["accompanyingProducts"],
                    array("table" => "shop_accomp", "key" => "id_price", "link" => "id_acc"));
        } catch (Exception $e) {
            $this->error = "Не удаётся сохранить сопутствующие товары!";
            throw new Exception($this->error);
        }
    }

    private function saveComments()
    {
        try {
            $idsProducts = $this->input["ids"];
            $comments = $this->input["comments"];
            $idsStr = implode(",", $idsProducts);
            $u = new DB('shop_comm', 'sc');
            $u->where('id_price IN (?)', $idsStr)->deleteList();
            foreach ($comments as $c) {
                $showing = 'N';
                $isActive = 'N';
                if ($c["isShowing"])
                    $showing = 'Y';
                if ($c["isActive"])
                    $isActive = 'Y';
                foreach ($idsProducts as $idProduct)
                    $data[] = array('id_price' => $idProduct, 'date' => $c["date"], 'name' => $c["contactTitle"],
                        'email' => $c["contactEmail"], 'commentary' => $c["commentary"], 'response' => $c["response"],
                        'showing' => $showing, 'is_active' => $isActive);
            }
            if (!empty($data))
                DB::insertList('shop_comm', $data);
        } catch (Exception $e) {
            $this->error = "Не удаётся сохранить комментарии товара!";
            throw new Exception($this->error);
        }
    }

    private function saveCrossGroups()
    {
        try {
            $idsProducts = $this->input["ids"];
            $groups = $this->input["crossGroups"];
            $idsStr = implode(",", $idsProducts);
            if (CORE_VERSION == "5.3") {
                $u = new DB('shop_price_group', 'spg');
                $u->where('NOT is_main AND id_price in (?)', $idsStr)->deleteList();
                foreach ($groups as $group)
                    foreach ($idsProducts as $idProduct)
                        $data[] = array('id_price' => $idProduct, 'id_group' => $group["id"], 'is_main' => 0);
                if (!empty($data)) {
                    DB::insertList('shop_price_group', $data);
                }
            } else
                foreach ($idsProducts as $id)
                    DB::saveManyToMany($id, $groups,
                        array("table" => "shop_group_price", "key" => "price_id", "link" => "group_id"));
        } catch (Exception $e) {
            $this->error = "Не удаётся сохранить дополнительные категории товара!";
            throw new Exception($this->error);
        }
    }

    private function saveDiscounts()
    {
        try {
            foreach ($this->input["ids"] as $id)
                DB::saveManyToMany($id, $this->input["discounts"],
                    array("table" => "shop_discount_links", "key" => "id_price", "link" => "discount_id"));
        } catch (Exception $e) {
            $this->error = "Не удаётся сохранить скидки товара!";
            throw new Exception($this->error);
        }
    }

    private function saveModifications()
    {
        try {
            $idsProducts = $this->input["ids"];
            $modifications = $this->input["modifications"];
            $idsStr = implode(",", $idsProducts);
            $isMultiMode = sizeof($idsProducts) > 1;

            $namesToIds = array();
            if (!empty($this->newImages)) {
                $imagesStr = '';
                foreach ($this->newImages as $image) {
                    if (!empty($imagesStr))
                        $imagesStr .= ',';
                    $imagesStr .= "'$image'";
                }
                $u = new DB('shop_img', 'si');
                $u->select('id, picture');
                $u->where('picture IN (?)', $imagesStr);
                $u->andWhere('id_price IN (?)', $idsStr);
                $objects = $u->getList();
                foreach ($objects as $item)
                    $namesToIds[$item['picture']] = $item['id'];
            }

            if (!$isMultiMode) {
                $idsUpdateM = null;
                foreach ($modifications as $mod) {
                    foreach ($mod["items"] as $item) {
                        if (!empty($item["id"])) {
                            if (!empty($idsUpdateM))
                                $idsUpdateM .= ',';
                            $idsUpdateM .= $item["id"];
                        }
                    }
                }
            }

            $u = new DB('shop_modifications', 'sm');
            if (!empty($idsUpdateM))
                $u->where("NOT id IN ($idsUpdateM) AND id_price in (?)", $idsStr)->deleteList();
            else $u->where("id_price IN (?)", $idsStr)->deleteList();

            // новые модификации
            $dataM = array();
            $dataF = array();
            $dataI = array();
            $result = DB::query("SELECT MAX(id) FROM shop_modifications")->fetch();
            $i = $result[0] + 1;
            foreach ($modifications as $mod) {
                foreach ($mod["items"] as $item) {
                    if (empty($item["id"]) || $isMultiMode) {
                        $count = null;
                        if ($item["count"] >= 0)
                            $count = $item["count"];
                        foreach ($idsProducts as $idProduct) {
                            $i++;
                            $dataM[] = array('id' => $i, 'code' => $item["article"],
                                'id_mod_group' => $mod["id"], 'id_price' => $idProduct, 'value' => $item["price"],
                                'value_opt' => $item["priceSmallOpt"], 'value_opt_corp' => $item["priceOpt"], 'count' => $count,
                                'sort' => (int) $item["sortIndex"], 'description' => $item["description"]);
                            foreach ($item["values"] as $v)
                                $dataF[] = array('id_price' => $idProduct, 'id_modification' => $i,
                                    'id_feature' => $v["idFeature"], 'id_value' => $v["id"]);
                            foreach ($item["images"] as $img) {
                                if ($img["id"] <= 0)
                                    $img["id"] = $namesToIds[$img["imageFile"]];
                                $dataI[] = array('id_modification' => $i, 'id_img' => $img["id"],
                                    'sort' => $img["sortIndex"]);
                            }
                        }
                    }
                }
            }
            if (!empty($dataM)) {
                DB::insertList('shop_modifications', $dataM);
                if (!empty($dataF))
                    DB::insertList('shop_modifications_feature', $dataF);
                if (!empty($dataI))
                    DB::insertList('shop_modifications_img', $dataI);
                $dataI = null;
            }

            // обновление модификаций
            if (!$isMultiMode) {
                foreach ($modifications as $mod) {
                    foreach ($mod["items"] as $item) {
                        if (!empty($item["id"])) {
                            $u = new DB('shop_modifications', 'sm');
                            $item["code"] = $item["article"];
                            $item["value"] = $item["price"];
                            $item["valueOpt"] = $item["priceOpt"];
                            $item["valueOptCorp"] = $item["priceSmallOpt"];
                            $item["sort"] = $item["sortIndex"];
                            $u->setValuesFields($item);
                            $u->save();

                            $u = new DB('shop_modifications_img', 'smi');
                            $u->where("id_modification = ?", $item["id"])->deleteList();
                            $dataI = array();
                            foreach ($item["images"] as $img) {
                                if ($img["id"] <= 0)
                                    $img["id"] = $namesToIds[$img["imageFile"]];
                                $dataI[] = array('id_modification' => $item["id"], 'id_img' => $img["id"],
                                    'sort' => $img["sortIndex"]);
                            }
                            if (!empty($dataI))
                                DB::insertList('shop_modifications_img', $dataI);
                        }
                    }
                }
            }
        } catch (Exception $e) {
            $this->error = "Не удаётся сохранить модификации товара!";
            throw new Exception($this->error);
        }
    }

    protected function saveAddInfo()
	{
        if (!$this->input["ids"])
            return false;

        $this->saveImages();
		$this->saveSpecifications();
        $this->saveSimilarProducts();
		$this->saveAccompanyingProducts();
        $this->saveComments();
        $this->saveCrossGroups();
        $this->saveDiscounts();
        $this->saveModifications();

		return true;
	}
}
