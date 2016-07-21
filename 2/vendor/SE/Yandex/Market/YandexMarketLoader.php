<?php

namespace SE\Yandex\Market;

use SE\DB;

class YandexMarketLoader {

    public function __construct($filename = null, $action = 'compare') {
        $this->loadFromMarket($filename);
    }

    private function loadFromMarket($filename) {
        if (file_exists($filename))
            $xml = simplexml_load_file($filename);
        else $xml = simplexml_load_string($filename);

        $this->ym->currency($xml->shop->currencies->children());
        $this->ym->category($xml->shop->categories->children());
        $this->ym->offer($xml->shop->offers->children());
    }


    public function getVendorModel($idProduct, $idModel, $idTypePrefix, $features = array()) {
        $model = $typePrefix = null;
        if (!empty($features)) {
            foreach ($features as $val) {
                if ($idModel == $val['id'])
                    $model = $val['value'];
                if ($idTypePrefix == $val['id'])
                    $typePrefix = $val['value'];
            }
        }
        if (!$model || !$typePrefix) {
            $shop_feature = new DB('shop_feature', 'sf');
            $shop_feature->select("DISTINCT sf.id,
				GROUP_CONCAT(CASE    
					WHEN (sf.type = 'list' OR sf.type = 'colorlist') THEN (SELECT sfvl.value FROM shop_feature_value_list sfvl WHERE sfvl.id = smf.id_value)
					WHEN (sf.type = 'number') THEN smf.value_number
					WHEN (sf.type = 'bool') THEN smf.value_bool
					WHEN (sf.type = 'string') THEN smf.value_string 
					ELSE NULL
				END SEPARATOR ', ') AS value
			");
            $shop_feature->innerJoin('shop_modifications_feature smf', 'sf.id = smf.id_feature');
            $shop_feature->where('smf.id_price = ?', $idProduct);
            $shop_feature->andWhere('smf.id_modification IS NULL');
            $shop_feature->andWhere('sf.id IN (?)', $idModel . ',' . $idTypePrefix);
            $shop_feature->groupBy('sf.id');
            $list = $shop_feature->getList();
            foreach ($list as $val) {
                if ($idModel == $val['id'])
                    $model = $val['value'];
                if ($idTypePrefix == $val['id'])
                    $typePrefix = $val['value'];
            }
        }

        return array($model, $typePrefix);
    }


    private function getDeliveries() {
        $delivery = new DB('shop_deliverygroup', 'sg');
        $delivery->select('sg.id_group, sd.price, sd.time');
        $delivery->innerJoin('shop_deliverytype sd', 'sd.id = sg.id_type');
        $delivery->where('sd.status = "Y"');
        $delivery->orderBy('sg.id_group');
        $delivery->addOrderBy('sd.time');
        $list = $delivery->getList();

        return $list;
    }

    private function getFeatureParams() {
        $params = array();
        $shop_feature = new seTable('shop_feature');
        $shop_feature->select('id, name, type, measure');
        $list = $shop_feature->getList();
        if (!empty($list)) {
            foreach ($list as $val) {
                $val['name'] = $this->replace($val['name']);
                $params[$val['id']] = $val;
            }
        }

        return $params;
    }

    private function getProductFeatures($id_product) {
        if (empty($id_product)) return;

        $shop_feature = new seTable('shop_feature', 'sf');
        $shop_feature->select("DISTINCT sf.id,
			GROUP_CONCAT(CASE    
				WHEN (sf.type = 'list' OR sf.type = 'colorlist') THEN (SELECT sfvl.value FROM shop_feature_value_list sfvl WHERE sfvl.id = smf.id_value)
				WHEN (sf.type = 'number') THEN smf.value_number
				WHEN (sf.type = 'bool') THEN smf.value_bool
				WHEN (sf.type = 'string') THEN smf.value_string 
				ELSE NULL
			END SEPARATOR ', ') AS value
		");
        $shop_feature->innerJoin('shop_modifications_feature smf', 'sf.id=smf.id_feature');
        $shop_feature->where('smf.id_price=?', $id_product);
        $shop_feature->andWhere('smf.id_modification IS NULL');
        $shop_feature->andWhere('sf.is_market=1');
        $shop_feature->groupBy('sf.id');
        $shop_feature->addOrderBy('sf.sort', 0);
        $list = $shop_feature->getList();

        return $list;
    }

    private function recursiveModifications($modifications = array()) {
        $result = array();
        if (!empty($modifications)) {
            $first = array_shift($modifications);
            if (!empty($modifications)) {
                $second = array_shift($modifications);
                foreach ($first as $val1) {
                    foreach ($second as $val2) {
                        $result[] = array(
                            //'name' => array_merge($val1['name'],  $val2['name']),
                            //'url' => $val1['url'] . '&' . $val2['url'],
                            'id'       => $val1['id'] . ',' . $val2['id'],
                            'features' => $val1['features'] + $val2['features']
                        );
                    }
                }
                array_unshift($modifications, $result);
                $result = $this->recursiveModifications($modifications);
            } else
                $result = $first;
        }

        return $result;
    }

    private function getProductModifications($id_price, $in_stock = true) {

        $shop_modifications = new seTable('shop_modifications', 'sm');
        $shop_modifications->select('sm.id, sm.id_mod_group as gid, (SELECT sort FROM shop_modifications_group WHERE sm.id_mod_group = id) AS gsort, GROUP_CONCAT(sf.id , "#!#", sf.name, "#!#", sfvl.value, "#!#", sfvl.id SEPARATOR "~!~") AS feature');
        $shop_modifications->innerJoin('shop_modifications_feature smf', 'sm.id=smf.id_modification');
        $shop_modifications->innerJoin('shop_feature sf', 'sf.id=smf.id_feature');
        $shop_modifications->innerjoin('shop_feature_value_list sfvl', 'smf.id_value=sfvl.id');
        $shop_modifications->where('sm.id_price=?', $id_price);
        if ($in_stock)
            $shop_modifications->andWhere('(sm.count <> 0 OR sm.count IS NULL)');
        $shop_modifications->groupBy('sm.id');
        $shop_modifications->orderBy('gsort', 0);
        $shop_modifications->addOrderBy('sf.sort', 0);
        $shop_modifications->addOrderBy('sfvl.sort', 0);
        //$shop_modifications->addOrderBy('sfvl.value', 0);
        $list = $shop_modifications->getList();

        $modifications = array();
        if (!empty($list)) {
            foreach ($list as $val) {
                if (!empty($val['feature'])) {

                    $feature_list = explode('~!~', $val['feature']);
                    foreach ($feature_list as $line) {
                        list($fid, $fname, $fvalue, $vid) = explode('#!#', $line);

                        $gid = $val['gid'];
                        $mid = $val['id'];

                        if (!isset($modifications[$gid][$mid])) {
                            $modifications[$gid][$mid] = array(
                                //'name' => '',
                                //'url' => 'm['.$gid.']='.$mid,
                                'id'       => $mid,
                                'features' => array()
                            );
                        }
                        $modifications[$gid][$mid]['features'][$fid] = $fvalue;
                        //$modifications[$gid][$mid]['name'][] = $fname . ': ' . $fvalue;
                    }

                }
            }
        }

        $modifications = $this->recursiveModifications($modifications);

        return $modifications;
    }

    private function shoppage($folder) {
        //  check business
        if (!file_exists('system/business')) {
            return false;
        }
        //  check pages
        $pages = simplexml_load_file('projects/' . $folder . 'pages.xml');
        foreach ($pages->page as $page) {
            $pagecontent = simplexml_load_file('projects/' . $folder . 'pages/' . $page['name'] . '.xml');
            foreach ($pagecontent->sections as $section) {
                if (strpos($section->type, 'shop_vitrine') !== false) {
                    return array('page' => $page['name'], 'id' => $section->id);
                }
            }
        }

        return false;
    }

    private function convert_curr($name) {
        return str_replace(array('KZT', 'BYR', 'RUB', 'UAH'), array('KAT', 'BER', 'RUR', 'UKH'), $name);
    }

    private function getBool($int) {
        return ($int) ? 'true' : 'false';
    }

    private function replace($text) {
        $search = array('&', '"', '>', '<', "'");
        $replace = array('&amp;', '&quot;', '&gt;', '&lt;', '&apos;');
        $text = str_replace($search, $replace, $text);

        return $text;
    }
}
