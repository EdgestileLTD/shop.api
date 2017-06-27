<?php

class Import
{

    private $patternsFieldsGoods = array(
        'id' => 'id',
        'isActiveIco' => 'enabled',
        'isNewIco' => 'flag_new',
        'isHitIco' => 'flag_hit',
        'code' => 'code',
        'article' => 'article',
        'name' => 'name',
        'наименование' => 'name',
        'price' => 'price',
        'цена' => 'price',
        'count' => 'presence_count',
        'количество' => 'presence_count',
        'idGroup' => 'id_group',
        'weight' => 'weight',
        'volume' => 'volume',
        'description' => 'note',
        'fullDescription' => 'text',
        'описание' => 'text',
        'measurement' => 'measure',
        'currency' => 'curr',
        'idBrand' => 'id_brand'
    );

    public $fieldsUpdatedGoods = array();
    public $idGroupDefault;
    private $idsExistsProducts = array();

    public function __construct($idShop = null)
    {
        if ($idShop)
            $this->idShop = $idShop;
        se_db_query("ALTER TABLE `shop_group` CHANGE `code_gr` `code_gr` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL;");
        se_db_query("ALTER TABLE `shop_price` CHANGE `code` `code` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL;");
    }

    public function dbTruncate()
    {
        se_db_query("SET foreign_key_checks = 0");
        se_db_query("TRUNCATE TABLE shop_group");
        se_db_query("TRUNCATE TABLE shop_price");
        se_db_query("TRUNCATE TABLE shop_brand");
        se_db_query("TRUNCATE TABLE shop_img");
        se_db_query("TRUNCATE TABLE shop_group_price");
        se_db_query("TRUNCATE TABLE shop_discounts");
        se_db_query("TRUNCATE TABLE shop_discount_links");
        se_db_query("TRUNCATE TABLE shop_modifications");
        se_db_query("TRUNCATE TABLE shop_modifications_group");
        se_db_query("TRUNCATE TABLE shop_feature_group");
        se_db_query("TRUNCATE TABLE shop_feature");
        se_db_query("TRUNCATE TABLE shop_group_feature");
        se_db_query("TRUNCATE TABLE shop_modifications_feature");
        se_db_query("TRUNCATE TABLE shop_feature_value_list");
        se_db_query("TRUNCATE TABLE shop_modifications_img");
        se_db_query("SET foreign_key_checks = 1");
    }

    public function dbTruncateShop()
    {
        se_db_query("SET foreign_key_checks = 0");
        se_db_query("DELETE FROM shop_group WHERE id_main={$this->idShop}");
        se_db_query("DELETE FROM shop_price WHERE id_main={$this->idShop}");
        se_db_query("DELETE FROM shop_brand WHERE id_main={$this->idShop}");
        se_db_query("DELETE FROM shop_img WHERE id_main={$this->idShop}");
        se_db_query("DELETE FROM shop_group_price WHERE id_main={$this->idShop}");
        se_db_query("DELETE FROM shop_discounts WHERE id_main={$this->idShop}");
        se_db_query("DELETE FROM shop_discount_links WHERE id_main={$this->idShop}");
        se_db_query("DELETE FROM shop_modifications WHERE id_main={$this->idShop}");
        se_db_query("DELETE FROM shop_modifications_group WHERE id_main={$this->idShop}");
        se_db_query("DELETE FROM shop_feature_group WHERE id_main={$this->idShop}");
        se_db_query("DELETE FROM shop_feature WHERE id_main={$this->idShop}");
        se_db_query("DELETE FROM shop_group_feature WHERE id_main={$this->idShop}");
        se_db_query("DELETE FROM shop_modifications_feature WHERE id_main={$this->idShop}");
        se_db_query("DELETE FROM shop_feature_value_list WHERE id_main={$this->idShop}");
        se_db_query("DELETE FROM shop_modifications_img WHERE id_main={$this->idShop}");
        se_db_query("SET foreign_key_checks = 1");
    }

    private function getContent($url)
    {
        $curl_handle = curl_init();
        curl_setopt($curl_handle, CURLOPT_URL, $url);
        curl_setopt($curl_handle, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($curl_handle, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl_handle, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 6.3; WOW64; rv:42.0) Gecko/20100101 Firefox/42.0');
        $result = curl_exec($curl_handle);
        curl_close($curl_handle);
        return $result;
    }

    public function loadImages($images)
    {
        foreach ($images as $image) {
            $url = $image["imageUrl"];
            $path = API_ROOT . '../images/rus/shopprice/' . $image["imageFile"];
            if (!file_exists($path))
                file_put_contents($path, $this->getContent($url));
        }
    }

    private function checkCode($code)
    {
        $code_n = $code;
        $u = new seTable('shop_price', 'sp');
        $i = 1;
        while ($i < 1000) {
            $u->findlist("sp.code='$code_n'")->fetchOne();
            if ($u->id)
                $code_n = $code . "-$i";
            else return $code_n;
            $i++;
        }
        return uniqid();
    }

    private function importImages($images, $keys)
    {
        $data = array();
        $keysProducts = array();
        foreach ($keys as $key => $values)
            $keysProducts[$values[0]] = $key;
        foreach ($images as $image) {
            if (isset($keysProducts[$image["key"]]))
                $data[] = array(
                    'id_price' => $keysProducts[$image["key"]],
                    'picture' => $image["imageFile"],
                    'picture_alt' => $image["imageAlt"],
                    'default' => $image["isMain"]
                );
        }
        if (!empty($data))
            $this->multi_query('shop_img', $data, array('id_price', 'picture'));
    }

    public function importProducts($products, $key = "article")
    {

        $data = array();
        $images = array();
        $specifications = array();
        $featuresValues = array();
        $pricesGroups = array();
        $codes = array();
        $i = 0;

        foreach ($products as $item) {
            $i++;
            $dit = array();
            $dit['code'] = se_translite_url($item['name']);
            if (in_array($dit["code"], $codes))
                $dit['code'] .= "_{$i}";
            $codes[] = $dit['code'];

            $dit['lang'] = 'rus';
            $dit['source'] = $item['source'];
            if ($this->idGroupDefault)
                $dit['id_group'] = $this->idGroupDefault;
            if (!empty($item['idGroup']))
                $dit['id_group'] = $item['idGroup'];
            if (!empty($item['article']))
                $dit['article'] = (!empty($item['article'])) ? $item['article'] : $this->maxArticle();
            if (!empty($item['name']))
                $dit['name'] = $item['name'];
            if (!empty($item['idBrand']))
                $dit['id_brand'] = (!empty($item['idBrand'])) ? $item['idBrand'] : '';
            if (!empty($item['price']))
                $dit['price'] = $item['price'];
            if (!empty($item['currency']))
                $dit['curr'] = $item['currency'];
            if (isset($item['count']))
                $dit['presence_count'] = "{$item['count']}";
            if (!empty($item['measurement']))
                $dit['measure'] = $item['measurement'];
            if (isset($item['volume']))
                $dit['volume'] = $item['volume'];
            if (isset($item['weight']))
                $dit['weight'] = $item['weight'];
            if (isset($item['description']))
                $dit['note'] = $item['description'];
            if (isset($item['fullDescription']))
                $dit['text'] = $item['fullDescription'];
            if (!empty($item["images"])) {
                $this->loadImages($item["images"]);
                foreach ($item["images"] as $itemImage) {
                    $image = $itemImage;
                    $image["key"] = $item[$key];
                    $images[] = $image;
                }
            }
            foreach ($item["specifications"] as $k => $v) {
                $isExist = false;
                foreach ($specifications as $specification)
                    if ($specification["id_price"] == $item[$key] && $specification['id_feature'] == $k && $specification["id_value"] == $v) {
                        $isExist = true;
                        break;
                    }
                if (!$isExist) {
                    $specification = array();
                    $specification["id_price"] = $item[$key];
                    $specification['id_feature'] = $k;
                    $specification["id_value"] = $v;
                    $specifications[] = $specification;
                }

                $isExist = false;
                foreach ($featuresValues as $featureValue)
                    if ($featureValue['id_feature'] == $k && $featureValue['value'] == $v) {
                        $isExist = true;
                        break;
                    }
                if (!$isExist) {
                    $featureValue = array();
                    $featureValue['id_feature'] = $k;
                    $featureValue['value'] = $v;
                    $featuresValues[] = $featureValue;
                }
            }
            if ($this->idGroupDefault)                 
                $pricesGroups[] = array("id_price" => $item[$key], "id_group" => $this->idGroupDefault);
            $data[] = $dit;
        }        
        $fieldsUpdates = array();
        if (!empty($this->fieldsUpdatedGoods))
            foreach ($this->fieldsUpdatedGoods as $field) {
                if (!empty($this->patternsFieldsGoods[$field]))
                    $fieldsUpdates[] = $this->patternsFieldsGoods[$field];
            }
        $fieldsUpdates[] = "source";    
        $keys = $this->multi_query('shop_price', $data, array("article", "source"), $fieldsUpdates);
        // картинки
        $this->importImages($images, $keys);
        // характеристики
        $idsProducts = array();
        foreach ($keys as $key => $values)
            $idsProducts[$values[0]] = $key;
        $keys = $this->multi_query('shop_feature_value_list', $featuresValues, array('id_feature', 'value'));
        $idsValues = array();
        foreach ($keys as $key => $values)
            $idsValues[$values[1]] = $key;
        // привязка к группе для ядра 5.3        
        $dataPricesGroups = array();                
        foreach ($pricesGroups as $priceGroup) {
            $priceGroup['id_price'] = $idsProducts[$priceGroup['id_price']];
            $priceGroup['is_main'] = true;
            $dataPricesGroups[] = $priceGroup;
        }        

        if ($dataPricesGroups)
            $this->multi_query('shop_price_group', $dataPricesGroups, array('id_price', 'id_group'));
        $dataSpecification = array();
        foreach ($specifications as $specification) {
            if (!in_array("характеристики", $this->fieldsUpdatedGoods) &&
                in_array($idsProducts[$specification['id_price']], $this->idsExistsProducts)
            )
                continue;
            $specification['id_price'] = $idsProducts[$specification['id_price']];
            $specification['id_value'] = $idsValues[$specification['id_value']];
            $dataSpecification[] = $specification;
        }
        se_db_query('DELETE FROM shop_modifications_feature 
            WHERE id_modification IS NULL AND id_value IS NULL AND value_number IS NULL AND value_bool IS NULL AND value_string IS NULL');
        $this->multi_query('shop_modifications_feature', $dataSpecification, array('id_price', 'id_feature', 'id_value'));

        return $idsProducts;
    }

    private function maxArticle()
    {
        $stmt = se_db_query("SELECT MAX(article + 1) FROM shop_price");
        if ($row = $stmt->fetch_row()) {
            if ($row[0])
                return $row[0];
            else return 1;
        } else return 1;
    }

    private function multi_query($table, $data = array(), $keys = null, $fieldsUpdates = null)
    {
        global $db_link;

        if (!$keys)
            $keys[] = 'id';
        if (!is_array($keys))
            $keys = array($keys);
        $strKeys = null;
        foreach ($keys as $key) {
            if ($strKeys)
                $strKeys .= ',';
            $strKeys .= '`' . $key . '`';
        }

        $articles = array();

        $listKeys = array();
        foreach ($keys as $key) {
            foreach ($data as $item) {
                if (!empty($item[$key]) && !in_array("'" . $item[$key] . "'", $listKeys[$key]))
                    $listKeys[$key][] = "'" . $item[$key] . "'";
            }
        }

        $updates = array();
        if (count($listKeys)) {
            $querySuffix = null;
            foreach ($keys as $key) {
                if ($querySuffix)
                    $querySuffix .= " AND ";
                if ($key == "source")    
                    $querySuffix .= "(`{$key}` IN (" . join(',', $listKeys[$key]) . ") OR source IS NULL)";
                else  $querySuffix .= "`{$key}` IN (" . join(',', $listKeys[$key]) . ")";
            }
            $res = mysqli_query($db_link, "SELECT {$strKeys} FROM `{$table}` WHERE {$querySuffix}");
            while ($row = mysqli_fetch_assoc($res))
                $updates[] = $row;
        }

        // Получаем подтверждение на обновление
        $query = null;
        foreach ($data as $item) {
            $fields = array();
            $values = array();
            foreach ($item as $field => $value) {
                $fields[] = $field;
                if (is_numeric($value) || $value == 'null')
                    $values[] = $value;
                else
                    $values[] = "'" . mysql_escape_string($value) . "'";
            }
            $isUpdate = false;
            foreach ($updates as $update) {
                $f = true;
                foreach ($keys as $key) {
                    if ($key == "source")
                        $f &= ($update[$key] == $item[$key] || empty($update[$key]));    
                    else $f &= ($update[$key] == $item[$key]);
                }
                $isUpdate |= $f;
            }
            if ($isUpdate) {                
                $prefix = "UPDATE `{$table}` SET ";
                $suffix = null;
                $where = null;
                foreach ($fields as $id => $fld) {
                    if (in_array($fld, $fieldsUpdates)) {
                        if (!empty($suffix))
                            $suffix .= ',';
                        $suffix .= '`' . $fld . '`=' . $values[$id];
                    }
                }
                foreach ($keys as $key) {
                    if (!empty($where))
                        $where .= ' AND ';
                    if ($key == "source")
                        $where .= "(`{$key}`='{$item[$key]}' OR `{$key}` IS NULL)";
                    else $where .= "`{$key}`='{$item[$key]}'";
                    if ($table == "shop_price" && $key == "article")
                        $articles[] = $item[$key];

                }
                if ($suffix)
                    $query .= $prefix . $suffix . " WHERE {$where};\n";
            } else {
                $fieldsStr = null;
                foreach ($fields as $field) {
                    if ($fieldsStr)
                        $fieldsStr .= ",";
                    $fieldsStr .= '`' . $field . '`';
                }
                $query .= "INSERT IGNORE INTO `{$table}`({$fieldsStr}) VALUES (" . join(',', $values) . ");\n";
            }
        }
         
        if (mysqli_multi_query($db_link, $query))
            while (mysqli_next_result($db_link)) {
                ;
            }

        $ids = array();
        $query = "SELECT id, {$strKeys} FROM `{$table}`";
        if ($result = mysqli_query($db_link, $query)) {
            while ($row = mysqli_fetch_row($result)) {
                $values = array();
                for ($i = 1; $i < count($row); ++$i)
                    $values[] = $row[$i];
                if ($table == "shop_price" && in_array($values[0], $articles))
                    $this->idsExistsProducts[] = $row[0];
                $ids[$row[0]] = $values;
            }
        }
        se_db_query("UPDATE main SET time_modified = " . time());
        return $ids;
    }

    public function correctFeatures()
    {

        return;

        global $db_link;

        // корректировка строк в списковые параметры
        $sql = "SELECT GROUP_CONCAT(id SEPARATOR ',') ids, COUNT(id) countAll FROM shop_modifications_feature smf
                WHERE (smf.value_string IS NOT NULL) AND (smf.value_string != '') AND (LENGTH(smf.value_string) < 64)
                GROUP BY smf.id_feature HAVING countAll > 2";
        $resultG = mysqli_query($db_link, $sql);
        while ($rowG = mysqli_fetch_assoc($resultG)) {
            $sql = "SELECT id, id_feature, value_string FROM shop_modifications_feature WHERE id IN ({$rowG['ids']})";
            $result = mysqli_query($db_link, $sql);
            while ($row = mysqli_fetch_assoc($result)) {
                $idValue = null;
                $sql = "SELECT id FROM shop_feature_value_list WHERE id_feature = {$row['id_feature']} AND `value` = '{$row['value_string']}'";
                $resultL = mysqli_query($db_link, $sql);
                if ($rowL = mysqli_fetch_assoc($resultL))
                    $idValue = $rowL['id'];
                else {
                    $sql = "INSERT INTO shop_feature_value_list (id_feature, `value`) VALUE ({$row['id_feature']}, '{$row['value_string']}')";
                    mysqli_query($db_link, $sql);
                    $idValue = mysqli_insert_id($db_link);
                }
                $sql = "UPDATE shop_modifications_feature SET value_string = NULL, id_value = {$idValue} WHERE id = {$row['id']}";
                mysqli_query($db_link, $sql);
                $sql = "UPDATE shop_feature SET `type` = 'list' WHERE id = {$row['id_feature']}";
                mysqli_query($db_link, $sql);
            }
        }

        // корректировка чисел в списковые параметры
        $sql = "SELECT GROUP_CONCAT(id SEPARATOR ',') ids, COUNT(id) countAll FROM shop_modifications_feature smf
                WHERE smf.value_number IS NOT NULL
                GROUP BY smf.id_feature HAVING countAll > 1 AND countAll < 10";
        $resultG = mysqli_query($db_link, $sql);
        while ($rowG = mysqli_fetch_assoc($resultG)) {
            $sql = "SELECT id, id_feature, value_number FROM shop_modifications_feature WHERE id IN ({$rowG['ids']})";
            $result = mysqli_query($db_link, $sql);
            while ($row = mysqli_fetch_assoc($result)) {
                $idValue = null;
                $sql = "SELECT id FROM shop_feature_value_list WHERE id_feature = {$row['id_feature']} AND `value` = '{$row['value_number']}'";
                $resultL = mysqli_query($db_link, $sql);
                if ($rowL = mysqli_fetch_assoc($resultL))
                    $idValue = $rowL['id'];
                else {
                    $sql = "INSERT INTO shop_feature_value_list (id_feature, `value`) VALUE ({$row['id_feature']}, '{$row['value_number']}')";
                    mysqli_query($db_link, $sql);
                    $idValue = mysqli_insert_id($db_link);
                }
                $sql = "UPDATE shop_modifications_feature SET value_number = NULL, id_value = {$idValue} WHERE id = {$row['id']}";
                mysqli_query($db_link, $sql);
                $sql = "UPDATE shop_feature SET `type` = 'list' WHERE id = {$row['id_feature']}";
                mysqli_query($db_link, $sql);
            }
        }
    }

}    
