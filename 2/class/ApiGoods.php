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
        $limit = empty($this->input['limit']) ? $this->limit : $this->input['limit'];
        $offset = empty($this->input['offset']) ? 0 : $this->input['offset'];

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
        $sql = $sqlPrefix . $sqlBody . " GROUP BY sp.id ORDER BY sp.id LIMIT {$offset}, {$limit}";
        $sqlCount = "SELECT COUNT(*) " . $sqlBody;

        $sth = $this->dbh->prepare($sql);
        $sthCount = $this->dbh->prepare($sqlCount);
        $params = array('id_lang' => $this->projectConfig["id_lang"]);
        $status = $sth->execute($params);
        $statusCount = $sthCount->execute($params);
        if ($status !== false && $statusCount !== false) {
            $items = $sth->fetchAll(PDO::FETCH_ASSOC);
            $itemsCount = $sthCount->fetchAll(PDO::FETCH_NUM);
            $result["response"]["count"] = $itemsCount[0][0];
            $result["response"]["items"] = $items;
            $result["response"]["server_time"] = time();
        } else {
            $this->isError = true;
            $result = 'Не удаётся получить список товаров!';
        }
        return $result;
    }

    protected function insert($items)
    {
        $itemsResult = array();
        $allowedFields = array("id_product", "id_lang", "name", "description", "content", "meta_title", "meta_keywords",
            "meta_description");
        if ($items) {
            $sqlProduct = "INSERT INTO shop_product (`url`) VALUE (:url)";
            $sthProduct = $this->dbh->prepare($sqlProduct);

            $sqlProductGroup = "INSERT INTO shop_product_group (id_product, id_group, is_main) VALUE (:id_product, :id_group, 1)";
            $sthProductGroup = $this->dbh->prepare($sqlProductGroup);

            $source = $items[0];
            $source["id_product"] = 0;
            $source["id_lang"] = $this->projectConfig["id_lang"];
            $sqlProductLang = "INSERT INTO shop_product_translate SET " . $this->pdoSet($allowedFields, $values, $source);
            $sthProductLang = $this->dbh->prepare($sqlProductLang);

            $sqlOffer = "INSERT INTO shop_offer (id_product, article) VALUE (:id_product, :article)";
            $sthOffer = $this->dbh->prepare($sqlOffer);

            $sqlOfferPrice = "INSERT INTO shop_offer_price (id_offer, id_typeprice, id_currency, `value`)
                              VALUE (:id_offer, :id_typeprice, :id_currency, :value)";
            $sthOfferPrice = $this->dbh->prepare($sqlOfferPrice);

            $sqlWarehouse = "INSERT INTO shop_warehouse_stock (id_warehouse, id_offer, `value`)
                             VALUE (:id_warehouse, :id_offer, :value)";
            $sthWarehouse = $this->dbh->prepare($sqlWarehouse);

            $sqlImage = "INSERT INTO shop_product_image (id_product, image, sort, is_main)
                         VALUE (:id_product, :image, :sort, :is_main)";
            $sthImage = $this->dbh->prepare($sqlImage);

            foreach ($items as $item) {
                $newItem = array();

                $params = array();
                $params["url"] = $this->getUrl($item["name"]);
                $sthProduct->execute($params);

                $params = array_merge($params, $item);
                $params["id_product"] = $this->dbh->lastInsertId();
                $params["id_lang"] = $this->projectConfig["id_lang"];
                foreach ($values as $key => &$val)
                    $val = $params[$key];
                $sthProductLang->execute($values);
                $newItem["id"] = $params["id_product"];

                if ($item["id_group"]) {
                    $params = array();
                    $params["id_product"] = $newItem["id"];
                    $params["id_group"] = $item["id_group"];
                    $sthProductGroup->execute($params);
                }

                $params = array();
                $params["id_product"] = $newItem["id"];
                $params["article"] = $item["article"];
                if (!$params["article"])
                    $params["article"] = $this->getArticle();
                $sthOffer->execute($params);

                $params = array();
                $idOffer = $this->dbh->lastInsertId();
                $params["id_offer"] = $idOffer;
                $params["id_typeprice"] = $this->idTypePrice;
                $params["id_currency"] = $this->idCurrency;
                $params["value"] = $item["price"] ? $item["price"] : 0;
                $sthOfferPrice->execute($params);

                if ($item["count"]) {
                    $params = array();
                    $params["id_warehouse"] = $this->idWarehouse;
                    $params["id_offer"] = $idOffer;
                    $params["value"] = $item["count"];
                    $sthWarehouse->execute($params);
                }

                if ($item["images"]) {
                    $i = 0;
                    foreach ($item["images"] as $image) {
                        $params = array();
                        $params["id_product"] = $newItem["id"];
                        $params["image"] = $image["image"];
                        $params["sort"] = $image["sort"];
                        $params["is_main"] = $image["is_main"] || !$i;
                        $sthImage->execute($params);
                        $i++;
                    }
                }

                $itemsResult["add"][] = $newItem;
            }
        }
        return $itemsResult;
    }

    protected function update($items)
    {
        $itemsResult = array();
        if ($items) {
            $updateItem = array();
            $allowedLang = array("name", "description", "content", "meta_title", "meta_keywords", "meta_description");
            $allowedProduct = array("id_brand", "id_measure", "url", "step_count", "max_discount", "is_discount", "is_visible", "is_unlimited");
            foreach ($items as $item) {
                $source = $item;
                $source["id_lang"] = $this->projectConfig["id_lang"];
                $source["id_product"] = $item["id"];

                $fieldsProduct = $this->pdoSet($allowedProduct, $valuesProduct, $source);
                $sqlProduct = "UPDATE shop_product SET {$fieldsProduct} WHERE id = :id";
                $sthProduct = $this->dbh->prepare($sqlProduct);

                $fieldsProductLang = $this->pdoSet($allowedLang, $valuesLang, $source);
                $sqlProductLang = "UPDATE shop_product_translate SET {$fieldsProductLang} WHERE id_product = :id AND id_lang = :id_lang";
                $sthProductLang = $this->dbh->prepare($sqlProductLang);

                $id_offer = null;
                $sqlOffer = "SELECT so.id FROM shop_offer so
                                INNER JOIN shop_offer_price sop ON sop.id_offer = so.id
                                WHERE so.id_product = :id_product";
                $sthOffer = $this->dbh->prepare($sqlOffer);
                $result = $sthOffer->execute(array("id_product" => $item["id"]));
                if ($result && ($items = $sthOffer->fetchAll(PDO::FETCH_ASSOC))) {
                    $id_offer = $items[0]["id"];
                }

                $sqlOfferPrice = "UPDATE shop_offer_price SET `value` = :value
                                    WHERE id_offer = :id_offer";
                $sthOfferPrice = $this->dbh->prepare($sqlOfferPrice);

                $sqlImage = "UPDATE shop_product_image SET image = :image, sort = :sort, is_main = :is_main
                              WHERE id_product = :id_product";
                $sthImage = $this->dbh->prepare($sqlImage);

                if (isset($item["count"]) && $id_offer) {
                    $sqlWarehouse = "SELECT sws.id FROM shop_warehouse_stock sws
                                        INNER JOIN shop_offer so ON so.id = sws.id_offer
                                        WHERE so.id = :id_offer";
                    $sthWarehouse = $this->dbh->prepare($sqlWarehouse);
                    $result = $sthWarehouse->execute(array("id_offer" => $id_offer));
                    if ($result && $sthWarehouse->fetchAll(PDO::FETCH_ASSOC)) {
                        $sqlWarehouse = "UPDATE shop_warehouse_stock SET `value` = :value WHERE id = :id";
                        $sthWarehouse = $this->dbh->prepare($sqlWarehouse);
                        $sthWarehouse->execute(array("id" => $item["id"], "value" => $item["count"]));
                    } else {
                        $sqlWarehouse = "INSERT INTO shop_warehouse_stock (id_warehouse, id_offer, `value`) VALUE (:id_warehouse, :id_offer, :value)";
                        $sthWarehouse = $this->dbh->prepare($sqlWarehouse);
                        $sthWarehouse->execute(array("id_warehouse" => $this->idWarehouse, "id_offer" => $id_offer, "value" => $item["count"]));
                    }
                }

                if ($fieldsProduct) {
                    foreach ($valuesProduct as $key => &$val)
                        $val = $item[$key];
                    $valuesProduct["id"] = $item["id"];
                    $sthProduct->execute($valuesProduct);
                }
                if ($fieldsProductLang) {
                    foreach ($valuesLang as $key => &$val)
                        $val = $item[$key];
                    $valuesLang["id"] = $item["id"];
                    $valuesLang["id_lang"] = $this->projectConfig["id_lang"];
                    $sthProductLang->execute($valuesLang);
                }
                if ($item["price"] && $id_offer) {
                    $sthOfferPrice->execute(array("id_offer" => $id_offer, "value" => $item["price"]));
                }
                if ($item["images"]) {
                    $i = 0;
                    foreach ($item["images"] as $image) {
                        $params = array();
                        $params["id_product"] = $item["id"];
                        $params["image"] = $image["image"];
                        $params["sort"] = $i;
                        $params["is_main"] = $image["is_main"] || !$i;
                        $sthImage->execute($params);
                        $i++;
                    }
                }

                //  изменение группы товара
                if($item['id_group'] > 0) {
                    $sqlProductGroup = 'SELECT id, id_group, is_main FROM shop_product_group WHERE id_product = :id';
                    $sthProductGroup = $this->dbh->prepare($sqlProductGroup);
                    $sthProductGroup->execute(array(":id" => $item["id"]));
                    $list = $sthProductGroup->fetchAll(PDO::FETCH_ASSOC);
                    $ids = array();
                    $is_main = '';  //  на будушее сделано, когда появятся несколько групп у товара
                    foreach($list as $line) {
                        $ids[] = $line['id'];
                        if($line['is_main'] > 0) {
                            $is_main = $line['id_group'];
                        }
                    }

                    if(!empty($ids)) {
                        if(!in_array($item['id_group'], $ids)) {
                            $ids_del = implode(",", $ids);
                            $this->dbh->query("DELETE FROM shop_product_group WHERE id IN ({$ids_del})");
                            //  foreach($item['id_group'] as $line) {
                                    $this->dbh->query("INSERT INTO shop_product_group (id_product, id_group, is_main) VALUES ({$item['id']}, {$item['id_group']}, 1);");
                            //  }
                        }
                    } else {
                        $this->dbh->query("INSERT INTO shop_product_group (id_product, id_group, is_main) VALUES ({$item['id']}, {$item['id_group']}, 1);");
                    }
                }
                $updateItem["id"] = $item["id"];
                $itemsResult["update"][] = $updateItem;
            }
        }
        return $itemsResult;
    }

    public function get()
    {
        $sql = "SELECT sp.id, spg.id_group, sgt.name group_name, sp.url, sp.is_discount, sp.is_visible,
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
            WHERE sp.id = :id
            GROUP BY sp.id";

        $sth = $this->dbh->prepare($sql);
        $params = array('id' => $this->input["id"]);
        $status = $sth->execute($params);
        if ($status !== false) {
            $items = $sth->fetchAll(PDO::FETCH_ASSOC);
            $result["response"]["items"] = $items;
            $result["response"]["server_time"] = time();
        } else {
            $this->isError = true;
            $result = 'Не удаётся получить информацию о товаре!';
        }
        return $result;
    }

    private function getUrl($name)
    {
        $url = strtolower(se_translite_url($name));
        $url_n = $url;
        $sql = 'SELECT url FROM shop_product WHERE url = :url';
        $sth = $this->dbh->prepare($sql);
        $i = 1;
        while ($i < 10) {
            $status = $sth->execute(array('url' => $url_n));
            if ($status !== false && $sth->fetch(PDO::FETCH_ASSOC))
                $url_n = $url . "-$i";
            else return $url_n;
            $i++;
        }
        return uniqid();
    }

    private function getArticle()
    {
        $sql = 'SELECT MAX(article + 1) FROM shop_offer';
        $result = $this->dbh->query($sql)->fetchAll(PDO::FETCH_NUM);
        $article = $result[0][0];
        $l = strlen($article);
        if ($l < 7)
            for ($i = 0; $i < (7 - $l); ++$i)
                $article = "0" . $article;

        return $article;
    }
}