<?php


class ApiGoods extends ApiBase
{
    private $idWarehouse = 1;
    private $idTypePrice = 2;
    private $idCurrency = 1;


    function __construct($input, $projectConfig, $dbConfig = null)
    {
        parent::__construct($input, $projectConfig, $dbConfig);
        $this->imageSection = "product";
    }

    public function fetch()
    {
        $images_url = $this->projectConfig["images_url"] . $this->imageSection . "/";
        //  limit
        $limit = '';
        if(isset($this->input['limit'])) {
            if(!isset($this->input['offset'])) {
                $limit = ' LIMIT ' . (int)$this->input['limit'];
            } else {
                $limit = ' LIMIT ' . (int)$this->input['offset'] . ',' . (int)$this->input['limit'];
            }
        }
        if(!isset($this->input['limit']) && isset($this->input['offset'])) {
            $limit = ' LIMIT ' . (int)$this->input['offset'] . ',999999999';
        }

        $sqlWhere = 'spt.id_lang = :id_lang';
        if (!empty($this->input['search'])) {
            $search = strtolower(trim($this->input['search']));
            $searchWhere = "(LOWER(spt.name) LIKE '%$search%' OR LOWER(sgt.name) LIKE '%$search%' OR LOWER(so.article) LIKE '$search%' OR sop.value LIKE '$search%')";
            $sqlWhere .= ' AND ' . $searchWhere;
        }

        $sqlPrefix = "SELECT sp.id, spg.id_group, sgt.name group_name, sp.url, sp.is_discount, sp.is_visible,
              sp.is_market, sp.is_unlimited, so.article, spt.name, spt.avilable_info, sop.value price, SUM(swt.value) `count`,
              IF(POSITION('http' IN spi.image), spi.image, CONCAT('{$images_url}', spi.image)) image ";
        $sqlBody = "FROM shop_product sp
            INNER JOIN shop_product_translate spt ON spt.id_product = sp.id
            LEFT JOIN shop_product_group spg ON spg.id_product = sp.id
            LEFT JOIN shop_group sg ON sg.id = spg.id_group
            LEFT JOIN shop_group_translate sgt ON sgt.id_group = sg.id
            INNER JOIN shop_offer so ON so.id_product = sp.id
            INNER JOIN shop_offer_price sop ON sop.id_offer = so.id
            LEFT JOIN shop_warehouse_stock swt ON swt.id_offer = so.id
            LEFT JOIN shop_product_image spi ON spi.id_product = sp.id AND spi.is_main
            WHERE {$sqlWhere}";
        $sql = $sqlPrefix . $sqlBody . " GROUP BY sp.id ORDER BY sp.id {$limit}";
        $sqlCount = "SELECT COUNT(*) AS count " . $sqlBody;

        $params = array('id_lang' => $this->projectConfig["id_lang"]);
        $items = ApiBase::$dbh->fetch($sql, $params);
        $status = ApiBase::$dbh->error;
        $itemsCount = ApiBase::$dbh->fetch($sqlCount, $params, true);
        $statusCount = ApiBase::$dbh->error;

        return (empty($status) && empty($statusCount))
            ? $this->generateData(["count" => "{$itemsCount['count']}", "items" => $items])
            : $this->generateData('Не удаётся получить список товаров', true, $status . ' ' . $statusCount);
    }

    public function create()
    {
        $items = $this->input;
        if (!empty($items)) {
            $allowedFields   = array("id_product", "id_lang", "name", "description", "content", "meta_title", "meta_keywords", "meta_description");
            $sqlProduct      = "INSERT INTO shop_product (`url`) VALUE (:url)";
            $sqlProductLang  = "INSERT INTO shop_product_translate (id_product, id_lang, `name`, description, content, meta_title, meta_keywords, meta_description)
                                VALUES (:id_product, :id_lang, :name, :description, :content, :meta_title, :meta_keywords, :meta_description)";
            $sqlProductGroup = "INSERT INTO shop_product_group (id_product, id_group, is_main)
                                VALUES (:id_product, :id_group, 1)";
            $sqlOffer        = "INSERT INTO shop_offer (id_product, article) VALUES (:id_product, :article)";
            $sqlOfferPrice   = "INSERT INTO shop_offer_price (id_offer, id_typeprice, id_currency, `value`)
                                VALUES (:id_offer, :id_typeprice, :id_currency, :value)";
            $sqlWarehouse    = "INSERT INTO shop_warehouse_stock (id_warehouse, id_offer, `value`)
                                VALUES (:id_warehouse, :id_offer, :value)";
            $sqlImage        = "INSERT INTO shop_product_image (id_product, image, sort, is_main)
                                VALUES (:id_product, :image, :sort, :is_main)";

            $empty = 0;
            $ok = $error = $warning = [];
            ApiBase::$dbh->setTransaction(false);
            foreach ($items as $item) {
                if(!is_array($item) || empty($item) || empty($item['name'])) {
                    ++$empty;
                    continue;
                }
                ApiBase::$dbh->startTransaction();
                $params = array();
                $params["url"] = (isset($item['url'])) ? strtolower($item['url']) : '';
                if(empty($params['url']) && !empty($item['name'])) {
                    $params['url'] = $this->getUrl($item['name']);
                }
                $newItem = ApiBase::$dbh->execute($sqlProduct, $params);
                if(!$newItem) {
                    $error[] = ApiBase::$dbh->error . ' Params:' . print_r($item, 1);
                    ApiBase::$dbh->cancelTransaction();
                    continue;
                }

                $tmp = array_merge($params, $item);
                $tmp["id_product"] = $newItem;
                $tmp["id_lang"] = $this->projectConfig["id_lang"];
                $params = ApiBase::$dbh->filterParams($allowedFields, $tmp);
                $ProductLang = ApiBase::$dbh->execute($sqlProductLang, $params);
                unset($tmp);
                if(!$ProductLang) {
                    $error[] = ApiBase::$dbh->error . '  Params:' . print_r($item, 1);
                    ApiBase::$dbh->cancelTransaction();
                    continue;
                }

                if (isset($item["id_group"])) {
                    $params = array();
                    $params["id_product"] = $newItem;
                    $params["id_group"]   = $item["id_group"];
                    $ProductGroup = ApiBase::$dbh->execute($sqlProductGroup, $params);
                    if(!$ProductGroup) {
                        $warning[] = ApiBase::$dbh->error . ' Params:' . print_r($item, 1);
                    }
                }

                $params = array();
                $params[":id_product"] = $newItem;
                $params[":article"]    = (isset($item["article"])) ? $item["article"] : '';
                if (!$params[":article"])
                    $params[":article"] = $this->getArticle();
                $idOffer = ApiBase::$dbh->execute($sqlOffer, $params);
                if(!$idOffer) {
                    $error[] = ApiBase::$dbh->error . ' Params:' . print_r($item, 1);
                    ApiBase::$dbh->cancelTransaction();
                    continue;
                }

                $params = array();
                $params[":id_offer"]     = $idOffer;
                $params[":id_typeprice"] = $this->idTypePrice;
                $params[":id_currency"]  = $this->idCurrency;
                $params[":value"] = (isset($item["price"])) ? $item["price"] : 0;
                $OfferPrice = ApiBase::$dbh->execute($sqlOfferPrice, $params);
                if(!$OfferPrice) {
                    $error[] = ApiBase::$dbh->error . ' Params:' . print_r($item, 1);
                    ApiBase::$dbh->cancelTransaction();
                    continue;
                }

                if (isset($item["count"])) {
                    $params = array();
                    $params[":id_warehouse"] = $this->idWarehouse;
                    $params[":id_offer"]     = $idOffer;
                    $params[":value"]        = $item["count"];
                    $Warehouse = ApiBase::$dbh->execute($sqlWarehouse, $params);
                    if(!$Warehouse) {
                        $warning[] = ApiBase::$dbh->error . ' Params:' . print_r($item, 1);
                    }
                }

                if (isset($item["images"])) {
                    $i = 0;
                    foreach ($item["images"] as $image) {
                        $params = array();
                        $params[":id_product"] = $newItem;
                        $params[":image"]      = $image["image"];
                        $params[":sort"]       = $image["sort"];
                        $params[":is_main"]    = $image["is_main"] || !$i;
                        $Image = ApiBase::$dbh->execute($sqlImage, $params);
                        if(!$Image) {
                            $warning[$newItem][] = ApiBase::$dbh->error . ' Params:' . print_r($item, 1);
                        }
                        $i++;
                    }
                }
                $ok[] = $newItem;
                ApiBase::$dbh->endTransaction();
            }
            ApiBase::$dbh->setTransaction();

            if($empty || $error || $warning)
                return $this->generateData(['empty' => $empty, 'error' => $error], true,
                    'Empty - кол-во записей без параметров, error - ошибки');

            $count = count($ok);
            return $this->generateData(["count" => "$count", "ids" => $ok, 'warning' => $warning]);
        }

        return $this->generateData('Не удаётся записать товары', true, 'Отсутствуют записи');
    }


    public function update()
    {
        $items = $this->input;
        if (!empty($items)) {
            $allowedFieldsSPT = array("name", "description", "content", "meta_title", "meta_description", "meta_keywords");
            $allowedFieldsSP = array("url", "is_discount", "is_visible", "is_market", "is_unlimited");
            $empty = 0;
            $error_update = $ids = [];
            ApiBase::$dbh->setTransaction(false);
            foreach($items as $item) {
                if(!isset($item['id']) || ((int)$item['id'] == 0)) {
                    ++$empty;
                    continue;
                }

                ApiBase::$dbh->startTransaction();

                $params = [];
                if(isset($item['url']) && !empty($item['url'])) $params['url'] = strtolower($item['url']);
                if(isset($item['is_discount']) && !empty($item['is_discount'])) $params['is_discount'] = $item['is_discount'];
                if(isset($item['is_visible']) && !empty($item['is_visible'])) $params['is_visible'] = $item['is_visible'];
                if(isset($item['is_market']) && !empty($item['is_market'])) $params['is_market'] = $item['is_market'];
                if(isset($item['is_unlimited']) && !empty($item['is_unlimited'])) $params['is_unlimited'] = $item['is_unlimited'];

                $param_product = ApiBase::$dbh->setParams($allowedFieldsSP, $params);
                if (!empty($param_product)) {
                    $sqlSP = "UPDATE shop_product SET {$param_product[1]} WHERE `id`=:id";
                    $param_product[0][':id'] = $item['id'];
                    $status = ApiBase::$dbh->execute($sqlSP, $param_product[0]);
                    if(!$status) {
                        ApiBase::$dbh->cancelTransaction();
                        $error_update[] = ApiBase::$dbh->error . ' Params: ' . print_r($item);
                        continue;
                    }
                }

                $params_offer = ApiBase::$dbh->setParams(['article'], $item);
                if (!empty($params_offer)) {
                    $params_offer[0][':id_product'] = $item['id'];
                    $sqlSO = "UPDATE shop_offer SET {$params_offer[1]} WHERE `id_product`=:id_product";
                    $status = ApiBase::$dbh->execute($sqlSO, $params_offer[0]);
                    if(!$status) {
                        ApiBase::$dbh->cancelTransaction();
                        $error_update[] = ApiBase::$dbh->error . ' Params: ' . print_r($item);
                        continue;
                    }
                }



                if (isset($item['groups']) && is_array($item['groups'])) {
                    $item['groups'] = array_filter($item['groups']);
                    $sqlSPGselect = "SELECT id_group as id FROM shop_product_group WHERE id_product = :id";
                    $groupsSPG = ApiBase::$dbh->fetch($sqlSPGselect, array(':id' => $item['id']));

                    $new_groupsSPG = $this->findNewGroups($item['groups'], $groupsSPG);
                    $del_groupsSPG = $this->findNewGroups($groupsSPG, $item['groups']);

                    $paramsSPG_new = $news = array();
                    foreach ($new_groupsSPG as $key => $new) {
                        $paramsSPG_new[':id' . $new] = $new;
                        $paramsSPG_new[':id_product' . $new] = $item['id'];
                        $news[] = "(:id_product{$new}, :id{$new}, " . ($key == 0 ? "1" : "0") . ")";
                    }

                    $tmp_del = $paramsSPG_del = array();
                    foreach ($del_groupsSPG as $del) {
                        $paramsSPG_del[':id' . $del] = $del;
                        $tmp_del[] = ':id' . $del;
                    }
                    $paramsSPG_del[':id_product'] = $item['id'];

                    if (count($del_groupsSPG) > 0) {
                        $sqlSPGdel = "DELETE FROM `shop_product_group`
                                  WHERE `id_product` = :id_product
                                  AND `id_group` IN (" . implode(',', $tmp_del) . ")";
                        $status = ApiBase::$dbh->execute($sqlSPGdel, $paramsSPG_del);
                        if (!$status) {
                            ApiBase::$dbh->cancelTransaction();
                            $error_update[] = ApiBase::$dbh->error . ' Params: ' . print_r($item);
                            continue;
                        }
                    }

                    if (count($new_groupsSPG) > 0) {
                        $sqlSPGnew = "INSERT INTO `shop_product_group` (`id_product`, `id_group`, `is_main`) VALUES
                                  ". implode(", ", $news);
                        $status = ApiBase::$dbh->execute($sqlSPGnew, $paramsSPG_new);
                        if(!$status) {
                            ApiBase::$dbh->cancelTransaction();
                            $error_update[] = ApiBase::$dbh->error . ' Params: ' . print_r($item);
                            continue;
                        }
                    }

                }

                $paramsSOP = [];
                if (isset($item['price']) && !empty($item['price']) && is_numeric($item['price'])) $paramsSOP['value'] = $item['price'];

                $params_offer_price = ApiBase::$dbh->setParams(['value'], $paramsSOP);
                if (!empty($params_offer_price)) {
                    $params_offer_price[0][':id_product'] = $item['id'];
                    $sqlSOP = "UPDATE shop_offer_price SET {$params_offer_price[1]}
                               WHERE `id_offer`=(SELECT id FROM `shop_offer` WHERE `id_product` = :id_product LIMIT 1)";
                    $status = ApiBase::$dbh->execute($sqlSOP, $params_offer_price[0]);
                    if(!$status) {
                        ApiBase::$dbh->cancelTransaction();
                        $error_update[] = ApiBase::$dbh->error . ' Params: ' . print_r($item);
                        continue;
                    }
                }

//                $params_group = ApiBase::$dbh->setParams('id_group', $item);
//                if (!empty($params_group)) {
//
//                }

                $params_lang = ApiBase::$dbh->setParams($allowedFieldsSPT, $item);
                if(!empty($params_lang)) {
                    $params_lang[0][':id_product'] = $item['id'];
                    $params_lang[0][':id_lang']  = $this->projectConfig["id_lang"];
                    $sqlSPT = "UPDATE shop_product_translate SET {$params_lang[1]} WHERE `id_product`=:id_product AND `id_lang`=:id_lang";
                    $status = ApiBase::$dbh->execute($sqlSPT, $params_lang[0]);
                    if(!$status) {
                        $error_update[] = ApiBase::$dbh->error . ' Params: ' . print_r($item);
                        ApiBase::$dbh->cancelTransaction();
                    } else {
                        $ids[] = $item['id'];
                        ApiBase::$dbh->endTransaction();
                    }
                }

                if(ApiBase::$dbh->inTransaction()) ApiBase::$dbh->endTransaction();
            }

            ApiBase::$dbh->setTransaction();

            if($empty || $error_update)
                return $this->generateData(['empty' => $empty, 'error' => $error_update], true,
                    'Empty - кол-во записей без параметров, error - ошибки');

            $count = count($ids);
            return $this->generateData(["ids" => $ids]);
        }

        return $this->generateData('Не удаётся изменить товары', true, 'Отсутствуют записи');

//        $itemsResult = array();
//        if ($items) {
//            $updateItem = array();
//            $allowedLang = array("name", "description", "content", "meta_title", "meta_keywords", "meta_description");
//            $allowedProduct = array("id_brand", "id_measure", "url", "step_count", "max_discount", "is_discount", "is_visible", "is_unlimited");
//            foreach ($items as $item) {
//                $source = $item;
//                $source["id_lang"] = $this->projectConfig["id_lang"];
//                $source["id_product"] = $item["id"];
//
//                $fieldsProduct = $this->pdoSet($allowedProduct, $valuesProduct, $source);
//                $sqlProduct = "UPDATE shop_product SET {$fieldsProduct} WHERE id = :id";
//                $sthProduct = $this->dbh->prepare($sqlProduct);
//
//                $fieldsProductLang = $this->pdoSet($allowedLang, $valuesLang, $source);
//                $sqlProductLang = "UPDATE shop_product_translate SET {$fieldsProductLang} WHERE id_product = :id AND id_lang = :id_lang";
//                $sthProductLang = $this->dbh->prepare($sqlProductLang);
//
//                $id_offer = null;
//                $sqlOffer = "SELECT so.id FROM shop_offer so
//                                INNER JOIN shop_offer_price sop ON sop.id_offer = so.id
//                                WHERE so.id_product = :id_product";
//                $sthOffer = $this->dbh->prepare($sqlOffer);
//                $result = $sthOffer->execute(array("id_product" => $item["id"]));
//                if ($result && ($items = $sthOffer->fetchAll(PDO::FETCH_ASSOC))) {
//                    $id_offer = $items[0]["id"];
//                }
//
//                $sqlOfferPrice = "UPDATE shop_offer_price SET `value` = :value
//                                    WHERE id_offer = :id_offer";
//                $sthOfferPrice = $this->dbh->prepare($sqlOfferPrice);
//
//                $sqlImage = "UPDATE shop_product_image SET image = :image, sort = :sort, is_main = :is_main
//                              WHERE id_product = :id_product";
//                $sthImage = $this->dbh->prepare($sqlImage);
//
//                if (isset($item["count"]) && $id_offer) {
//                    $sqlWarehouse = "SELECT sws.id FROM shop_warehouse_stock sws
//                                        INNER JOIN shop_offer so ON so.id = sws.id_offer
//                                        WHERE so.id = :id_offer";
//                    $sthWarehouse = $this->dbh->prepare($sqlWarehouse);
//                    $result = $sthWarehouse->execute(array("id_offer" => $id_offer));
//                    if ($result && $sthWarehouse->fetchAll(PDO::FETCH_ASSOC)) {
//                        $sqlWarehouse = "UPDATE shop_warehouse_stock SET `value` = :value WHERE id = :id";
//                        $sthWarehouse = $this->dbh->prepare($sqlWarehouse);
//                        $sthWarehouse->execute(array("id" => $item["id"], "value" => $item["count"]));
//                    } else {
//                        $sqlWarehouse = "INSERT INTO shop_warehouse_stock (id_warehouse, id_offer, `value`) VALUE (:id_warehouse, :id_offer, :value)";
//                        $sthWarehouse = $this->dbh->prepare($sqlWarehouse);
//                        $sthWarehouse->execute(array("id_warehouse" => $this->idWarehouse, "id_offer" => $id_offer, "value" => $item["count"]));
//                    }
//                }
//
//                if ($fieldsProduct) {
//                    foreach ($valuesProduct as $key => &$val)
//                        $val = $item[$key];
//                    $valuesProduct["id"] = $item["id"];
//                    $sthProduct->execute($valuesProduct);
//                }
//                if ($fieldsProductLang) {
//                    foreach ($valuesLang as $key => &$val)
//                        $val = $item[$key];
//                    $valuesLang["id"] = $item["id"];
//                    $valuesLang["id_lang"] = $this->projectConfig["id_lang"];
//                    $sthProductLang->execute($valuesLang);
//                }
//                if ($item["price"] && $id_offer) {
//                    $sthOfferPrice->execute(array("id_offer" => $id_offer, "value" => $item["price"]));
//                }
//                if ($item["images"]) {
//                    $i = 0;
//                    foreach ($item["images"] as $image) {
//                        $params = array();
//                        $params["id_product"] = $item["id"];
//                        $params["image"] = $image["image"];
//                        $params["sort"] = $i;
//                        $params["is_main"] = $image["is_main"] || !$i;
//                        $sthImage->execute($params);
//                        $i++;
//                    }
//                }
//
//                //  изменение группы товара
//                if($item['id_group'] > 0) {
//                    $sqlProductGroup = 'SELECT id, id_group, is_main FROM shop_product_group WHERE id_product = :id';
//                    $sthProductGroup = $this->dbh->prepare($sqlProductGroup);
//                    $sthProductGroup->execute(array(":id" => $item["id"]));
//                    $list = $sthProductGroup->fetchAll(PDO::FETCH_ASSOC);
//                    $ids = array();
//                    $is_main = '';  //  на будушее сделано, когда появятся несколько групп у товара
//                    foreach($list as $line) {
//                        $ids[] = $line['id'];
//                        if($line['is_main'] > 0) {
//                            $is_main = $line['id_group'];
//                        }
//                    }
//
//                    if(!empty($ids)) {
//                        if(!in_array($item['id_group'], $ids)) {
//                            $ids_del = implode(",", $ids);
//                            $this->dbh->query("DELETE FROM shop_product_group WHERE id IN ({$ids_del})");
//                            //  foreach($item['id_group'] as $line) {
//                            $this->dbh->query("INSERT INTO shop_product_group (id_product, id_group, is_main) VALUES ({$item['id']}, {$item['id_group']}, 1);");
//                            //  }
//                        }
//                    } else {
//                        $this->dbh->query("INSERT INTO shop_product_group (id_product, id_group, is_main) VALUES ({$item['id']}, {$item['id_group']}, 1);");
//                    }
//                }
//                $updateItem["id"] = $item["id"];
//                $itemsResult["update"][] = $updateItem;
//            }
//        }
//        return $itemsResult;
    }

    public function get()
    {
        $items = (!empty($this->input['ids'])) ? $this->input['ids'] : false;
        if(!is_array($items)) {
            return $this->generateData('Не удаётся получить информацию о товарах', true, 'Отсутствует параметр ids (ids:array)');
        }

        $tmp = array();
        $params = array();
        foreach($items as $item) {
            $params[':id'.$item] = $item;
            $tmp[] = ':id'.$item;
        }
        $sqlWhere = 'sp.`id` IN (' . implode(',', $tmp). ')';


        $sql = "SELECT sp.id, GROUP_CONCAT(concat_ws('%%', sgt.id_group, sgt.name) SEPARATOR '||') groups, sp.url, sp.is_discount, sp.is_visible,
                  sp.is_market, sp.is_unlimited, so.article, spt.name, spt.description, spt.content,
                  spt.meta_title, spt.meta_keywords, spt.meta_description,
                  spt.avilable_info, sop.value price, SUM(swt.value) `count`,
                  IF(POSITION('http' IN spi.image), spi.image, spi.image) image FROM shop_product sp
                INNER JOIN shop_product_translate spt ON spt.id_product = sp.id
                LEFT JOIN shop_product_group spg ON spg.id_product = sp.id
                LEFT JOIN shop_group sg ON sg.id = spg.id_group
                LEFT JOIN shop_group_translate sgt ON sgt.id_group = sg.id
                INNER JOIN shop_offer so ON so.id_product = sp.id
                INNER JOIN shop_offer_price sop ON sop.id_offer = so.id
                LEFT JOIN shop_warehouse_stock swt ON swt.id_offer = so.id
                LEFT JOIN shop_product_image spi ON spi.id_product = sp.id AND spi.is_main
                WHERE {$sqlWhere}
                GROUP BY sp.id";

        $items = ApiBase::$dbh->fetch($sql, $params);
//        if (empty(ApiBase::$dbh->error)) {
//            $result["response"]["items"] = $items;
//            $result["response"]["server_time"] = time();
//        } else {
//            $this->isError = true;
//            $result["response"]["error"] = 'Не удаётся получить информацию о товаре!';
//        }
        $count = count($items);

        if (empty(ApiBase::$dbh->error)) {
            for ($i = 0; $i < count($items); $i++) {
                if (!empty($items[$i]['groups'])) {
                    $tmp = explode('||', $items[$i]['groups']);
                    $items[$i]['groups'] = [];
                    for ($k = 0; $k < count($tmp) ; $k++) {
                        $item = [];
                        list($item['id'], $item['title']) = explode('%%', $tmp[$k]);
                        $items[$i]['groups'][] = $item;
                    }
                }
            }
        }

        return (empty(ApiBase::$dbh->error))
            ? $this->generateData(["count" => "$count", "items" => $items])
            : $this->generateData('Не удаётся получить информацию о товаре', true, ApiBase::$dbh->error);
    }

    private function findNewGroups($needle, $haystack) {
        $tmp = array();
        for ($i = 0; $i < count($needle); $i++) {
            $find = false;
            for ($k = 0; $k < count($haystack); $k++) {
                if (in_array($needle[$i]['id'], $haystack[$k])) {
                    $find = true;
                    break;
                }
            }
            if (!$find) {
                $tmp[] = $needle[$i]['id'];
            }
        }
        return $tmp;
    }

    private function getUrl($name)
    {
        $url = strtolower($this->translit_str($name));
        $url_n = $url;
        $sql = 'SELECT url FROM shop_product WHERE url = :url';
        $i = 1;
        while ($i < 10) {
            $status = ApiBase::$dbh->fetch($sql, array('url' => $url_n), true);
            if (!empty($status))
                $url_n = $url . "-$i";
            else return $url_n;
            $i++;
        }
        return uniqid();
    }

    private function getArticle()
    {
        $sql = 'SELECT MAX(article + 1) AS m FROM shop_offer';
        $result = ApiBase::$dbh->fetch($sql, [], true);
        $article = $result['m'];
        $l = strlen($article);
        if ($l < 7)
            for ($i = 0; $i < (7 - $l); ++$i)
                $article = "0" . $article;

        return $article;
    }
}