<?php

namespace SE\Yandex\Market; 

abstract class AYandexMarket {
    private $currencyList = null;
    private static $cbrCode = null;
    private $storeCurrency = array();
    private $categoryList = null;
    protected $categoryTree = array();
    private $storeCategory = array();
    private $brandList = null;
    private $storeBrand = array();
    private $productList = null;
    private $storeProduct = array();
    protected $productParam = array();
    private $paramList = null;
    private $productListByCode = null;
    private $storeParam = array();
    private $paramValueList = array();
    private $storeParamValue = array();
    private $storeProductParamValue = array();
    private $pictureList = null;
    protected $pictureNewList = array();
    private $storePicture = array();

    private $codeList;

    abstract public function currency($currencies);

    abstract public function category($groups);

    abstract public function offer($offers);

    protected function getCurrency() {
        if ($this->currencyList == null) {
            $tmp = array();
            $valute = new seTable('money_title');
            $valute->select('DISTINCT name');
            $valute = $valute->getList();
            foreach ($valute as $item) {
                $tmp[] = $item['name'];
            }

            $this->currencyList = $tmp;
        }

        return $this->currencyList;
    }

    protected function sbrCode() {
        if (self::$cbrCode == null) {
            $cbr_code = json_decode('{"AUD":{"name":"\u0410\u0432\u0441\u0442\u0440\u0430\u043b\u0438\u0439\u0441\u043a\u0438\u0439 \u0434\u043e\u043b\u043b\u0430\u0440","code":"R01010"},"GBP":{"name":"\u0424\u0443\u043d\u0442 \u0441\u0442\u0435\u0440\u043b\u0438\u043d\u0433\u043e\u0432 \u0421\u043e\u0435\u0434\u0438\u043d\u0435\u043d\u043d\u043e\u0433\u043e \u043a\u043e\u0440\u043e\u043b\u0435\u0432\u0441\u0442\u0432\u0430","code":"R01035"},"BYR":{"name":"\u0411\u0435\u043b\u043e\u0440\u0443\u0441\u0441\u043a\u0438\u0445 \u0440\u0443\u0431\u043b\u0435\u0439","code":"R01090"},"DKK":{"name":"\u0414\u0430\u0442\u0441\u043a\u0438\u0445 \u043a\u0440\u043e\u043d","code":"R01215"},"USD":{"name":"\u0414\u043e\u043b\u043b\u0430\u0440 \u0421\u0428\u0410","code":"R01235"},"EUR":{"name":"\u0415\u0432\u0440\u043e","code":"R01239"},"ISK":{"name":"\u0418\u0441\u043b\u0430\u043d\u0434\u0441\u043a\u0438\u0445 \u043a\u0440\u043e\u043d","code":"R01310"},"KZT":{"name":"\u041a\u0430\u0437\u0430\u0445\u0441\u0442\u0430\u043d\u0441\u043a\u0438\u0445 \u0442\u0435\u043d\u0433\u0435","code":"R01335"},"CAD":{"name":"\u041a\u0430\u043d\u0430\u0434\u0441\u043a\u0438\u0439 \u0434\u043e\u043b\u043b\u0430\u0440","code":"R01350"},"NOK":{"name":"\u041d\u043e\u0440\u0432\u0435\u0436\u0441\u043a\u0438\u0445 \u043a\u0440\u043e\u043d","code":"R01535"},"XDR":{"name":"\u0421\u0414\u0420 (\u0441\u043f\u0435\u0446\u0438\u0430\u043b\u044c\u043d\u044b\u0435 \u043f\u0440\u0430\u0432\u0430 \u0437\u0430\u0438\u043c\u0441\u0442\u0432\u043e\u0432\u0430\u043d\u0438\u044f)","code":"R01589"},"SGD":{"name":"\u0421\u0438\u043d\u0433\u0430\u043f\u0443\u0440\u0441\u043a\u0438\u0439 \u0434\u043e\u043b\u043b\u0430\u0440","code":"R01625"},"TRL":{"name":"\u0422\u0443\u0440\u0435\u0446\u043a\u0438\u0445 \u043b\u0438\u0440","code":"R01700"},"UAH":{"name":"\u0423\u043a\u0440\u0430\u0438\u043d\u0441\u043a\u0438\u0445 \u0433\u0440\u0438\u0432\u0435\u043d","code":"R01720"},"SEK":{"name":"\u0428\u0432\u0435\u0434\u0441\u043a\u0438\u0445 \u043a\u0440\u043e\u043d","code":"R01770"},"CHF":{"name":"\u0428\u0432\u0435\u0439\u0446\u0430\u0440\u0441\u043a\u0438\u0439 \u0444\u0440\u0430\u043d\u043a","code":"R01775"},"JPY":{"name":"\u042f\u043f\u043e\u043d\u0441\u043a\u0438\u0445 \u0438\u0435\u043d","code":"R01820"}}', 1);
            self::$cbrCode = $cbr_code;
        }

        return self::$cbrCode;
    }

    protected function setCurrency($item = array()) {
        if (!empty($item)) {
            $this->storeCurrency[] = $item;
            $this->currencyList[] = $item['name'];
        }
    }

    protected function getCategory() {
        if ($this->categoryList == null) {
            $this->codeList = $category = array();
            $pg = plugin_shopgroups::getInstance();
            $tmp = $pg->getAllGroups();
            foreach ($tmp as $k => $v) {
                if (is_numeric($k)) {
                    $category[$k] = $v['name'];
                    $this->codeList[] = $v['code'];
                }
            }

            $this->categoryList = $category;
        }

        return $this->categoryList;
    }

    protected function setCategory($item = array()) {
        if (!empty($item)) {
            $this->storeCategory = array($item);
            $this->categoryList[] = $item['name'];
        }
    }

    protected function getBrand() {
        if ($this->brandList == null) {
            $brand = new seTable('shop_brand');
            $brand->select("id, name");
            $brandlist = $brand->getList();

            $out = array();
            foreach ($brandlist as $item) {
                $out[$item['id']] = $item['name'];
            }

            $this->brandList = $out;
            unset($brand, $brandlist, $out);
        }

        return $this->brandList;
    }

    protected function setBrand($item = array()) {
        if (!empty($item)) {
            $this->storeBrand = array($item);
        }
    }

    protected function addBrand($key, $value) {
        $this->brandList[$key] = $value;
    }

    protected function getProduct() {
        if ($this->productList == null) {
            $shop_price = new seTable('shop_price', 'sp');
            $shop_price->select("sp.id, sp.name, sp.price, sp.code,
			(SELECT GROUP_CONCAT(sha.id_acc) FROM shop_accomp sha WHERE sha.id_price=sp.id) as rec, 
			(SELECT b.name FROM shop_brand b WHERE b.id=sp.id_brand) as brand, 
			(SELECT 1 FROM shop_modifications sm WHERE sm.id_price=sp.id LIMIT 1) AS modifications");
//            $shop_price->innerjoin('shop_group sg', 'sg.id=sp.id_group');
            $shop_price->where('sp.`name`<>""');
            //            $shop_price->andWhere('sp.`enabled`="Y"');
            $shop_price->andWhere('sp.lang="rus"');
            //            $shop_price->having('sp.price > 0');
            $pricelist = $shop_price->getList();

            $this->codeList = $output = array();
            foreach ($pricelist as $item) {
                $output[] = $item['name'];
                $this->codeList[] = $item['code'];
            }

            $this->productList = $output;
            unset($shop_price, $pricelist, $output);
        }
        return $this->productList;
    }

    protected function setProduct($item = array()) {
        if (!empty($item)) {
            $this->storeProduct[] = $item;
            $this->productList[] = $item['name'];
        }
    }

    protected function getParam() {
        if ($this->paramList == null) {
            $shop_feature = new seTable('shop_feature', 'sf');
            $shop_feature->select("sf.id, sf.name, 
            (SELECT GROUP_CONCAT(sfl.value SEPARATOR '|||') FROM `shop_feature_value_list` `sfl` WHERE sfl.id_feature=sf.id) as `values`,
            (SELECT GROUP_CONCAT(sfl.id SEPARATOR '|||') FROM `shop_feature_value_list` `sfl` WHERE sfl.id_feature=sf.id) as `ids`");
            $list = $shop_feature->getList();

            $shop_feature_addon = new seTable('shop_feature_value_list', 'sfl');
            $shop_feature_addon->select('sf.id, sfl.id as ids, sfl.value');
            $shop_feature_addon->innerjoin('shop_feature sf', 'sfl.id_feature=sf.id');
            $shop_feature_addonList = $shop_feature_addon->getList();

            $shop_feature_value_list = array();
            foreach ($shop_feature_addonList as $item) {
                $shop_feature_value_list[$item['id']][$item['ids']] = $item['value'];
            }

            $output = $outputValue = array();
            foreach ($list as $item) {
                $output[$item['id']] = $item['name'];
                if (!empty($shop_feature_value_list[$item['id']])) {
                    $outputValue[$item['id']] = $shop_feature_value_list[$item['id']];
                }
//                if (!empty($item['values'])) {
//                    $ids = explode("|||", $item['ids']);
//                    $values = explode("|||", trim($item['values']));
//                    $outputValue[$item['id']] = array_combine($ids, $values);
//                }
            }
            $this->paramList = $output;
            $this->paramValueList = $outputValue;
            unset($shop_feature, $list, $output, $outputValue);
        }

        return $this->paramList;
    }

    protected function setParam($item = array()) {
        if (!empty($item)) {
            $this->storeParam = array($item);
        }
    }

    protected function addParam($key, $value) {
        $this->paramList[$key] = $value;
    }
    
    protected function getProductByCode() {
        if ($this->productListByCode == null) {
            $shop_price = new seTable('shop_price', 'sp');
            $shop_price->select('sp.id, sp.code');
//            $shop_price->innerjoin('shop_group sg', 'sg.id=sp.id_group');
            $shop_price->where('sp.`name`<>""');
            $shop_price->andWhere('sp.lang="rus"');
            $pricelist = $shop_price->getList();

            $out = array();
            foreach ($pricelist as $item) {
                $out[$item['code']] = $item['id'];
            }

            $this->productListByCode = $out;
            unset($shop_price, $pricelist, $out);
        }

        return $this->productListByCode;
    }

    protected function getParamValue($id = 0) {
        return (!empty($id) && !empty($this->paramValueList[$id]))
            ? $this->paramValueList[$id] : array();
    }

    protected function setParamValue($item = array()) {
        if (!empty($item)) {
            $this->storeParamValue = array($item);
        }
    }

    protected function addParamValue($key, $value) {
        $this->paramValueList[$key] = $value;
    }

    protected function setProductParamValue($item = array()) {
        if (!empty($item)) {
            $this->storeProductParamValue[] = $item;
        }
    }

    protected function getTranslitName($str, $delimer = '-') {
        $translate = array(
            'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'ж' => 'g', 'з' => 'z',
            'и' => 'i', 'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n', 'о' => 'o', 'п' => 'p',
            'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u', 'ф' => 'f', 'ы' => 'i', 'э' => 'e', 'А' => 'a',
            'Б' => 'b', 'В' => 'v', 'Г' => 'g', 'Д' => 'd', 'Е' => 'e', 'Ж' => 'g', 'З' => 'z', 'И' => 'i',
            'Й' => 'y', 'К' => 'k', 'Л' => 'l', 'М' => 'm', 'Н' => 'n', 'О' => 'o', 'П' => 'p', 'Р' => 'r',
            'С' => 's', 'Т' => 't', 'У' => 'u', 'Ф' => 'f', 'Ы' => 'i', 'Э' => 'e', 'ё' => "yo", 'х' => "h",
            'ц' => "ts", 'ч' => "ch", 'ш' => "sh", 'щ' => "shch", 'ъ' => "", 'ь' => "", 'ю' => "yu", 'я' => "ya",
            'Ё' => "yo", 'Х' => "h", 'Ц' => "ts", 'Ч' => "ch", 'Ш' => "sh", 'Щ' => "shch", 'Ъ' => "", 'Ь' => "",
            'Ю' => "yu", 'Я' => "ya", '№' => "", " " => "-", '"' => 'qout', "'" => '039', '&' => 'amp',
            ',' => 'comma', ';' => 'smcl', '.' => 'dot'
        );
        $string = strtr($str, $translate);
        $string = trim(preg_replace('/[^\w]+/ui', $delimer, $string), $delimer);
        if (!in_array($string, $this->codeList)) {
            $this->codeList[] = $string;

            return $string;
        }

        $string_uniq = $string . uniqid();
        $this->codeList[] = $string_uniq;

        return $string_uniq;
    }

    protected function getPicture() {
        if ($this->pictureList == null) {
            $pictures = new seTable('shop_img', 'si');
            $pictures->select('sp.id si.picture, sp.name');
            $pictures->innerjoin('shop_price sp', 'sp.id=si.id_price');
            $list = $pictures->getList();

            $output = array();
            foreach ($list as $item) {
                $output[$item['id']][] = $item;
            }
            
            $this->pictureList = $output;
            unset($pictures, $list);
        }

        return $this->pictureList;
    }
    
    protected function setPicture($item = array()) {
        if (!empty($item)) {
            $this->storePicture[] = $item;
        }
    }

    public function save($table = '') {
        switch ($table) {
            case 'money_title':
                $data = $this->storeCurrency;
                break;
            case 'shop_group':
                $data = $this->storeCategory;
                break;
            case 'shop_brand':
                $data = $this->storeBrand;
                break;
            case 'shop_price':
                $data = $this->storeProduct;
                break;
            case 'shop_feature':
                $data = $this->storeParam;
                break;
            case 'shop_feature_value_list':
                $data = $this->storeParamValue;
                break;
            case 'shop_modifications_feature':
                $data = $this->storeProductParamValue;
                break;
            case 'shop_img':
                $data = $this->storePicture;
                break;
            default:
                $data = array();
        }

        if (empty($table) || empty($data)) return true;
        try {
            se_db_InsertList($table, $data);
            echo $table . ': ' . se_db_error() . "<br>";
            return se_db_insert_id($table);
        } catch (Exception $e) {
            echo "<br>" . $e->getMessage();
            echo "<br>" . se_db_error();
        }

        return true;
    }

}