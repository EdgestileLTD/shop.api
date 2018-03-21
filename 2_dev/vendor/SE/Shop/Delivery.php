<?php

namespace SE\Shop;

use SE\DB as DB;
use SE\Exception;

class Delivery extends Base
{
    protected $tableName = "shop_deliverytype";

    // получить
    public function fetch()
    {
        /** Получить данные по доставке
         * 1 вытаскиваем данные из DB shop_deliverytype
         * 2 получаем данные по базовой валюте
         * 3 переводим значения в баз-валюту и добавляем обозначения
         */
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');
        try {
            $u = new DB('shop_deliverytype', 'sd'); // 1
            $u->select('sd.*');
            $u->where('sd.id_parent IS NULL');
            $u->orderBy('sort', false);
            $objects = $u->getList();
            unset($u);

            $u = new DB('main', 'm'); // 2
            $u->select('mt.name, mt.title, mt.name_front');
            $u->innerJoin('money_title mt', 'm.basecurr = mt.name');
            $this->currData = $u->fetchOne();
            unset($u);

            $account = array();
            foreach ($objects as $item) {
                $item['balance'] = $balance;

                $course = DB::getCourse($this->currData["name"], $item["curr"]); // 3
                $convertingValues = array('price');
                foreach ($convertingValues as $key => $i) {
                    $item[$i] = $item[$i] * $course;
                }
                unset($item["curr"]);
                $item["nameFlang"] = $this->currData["name"];
                $item["titleCurr"] = $this->currData["title"];
                $item["nameFront"] = $this->currData["nameFront"];
                $account[] = $item;


                $delivery = $item;
                if (empty($delivery['code']))
                    $delivery['code'] = "simple";
                $delivery['period'] = $item['time'];
                $delivery['idCityFrom'] = $item['cityFromDelivery'];
                $delivery['isActive'] = ($item['status'] == 'Y');
                $delivery['currency'] = $item['curr'];
                $delivery['onePosition'] = $item['forone'] == 'Y';
                $delivery['needAddress'] = $item['needAddress'] == 'Y';
                $items[] = $delivery;
            }

            $data['count'] = sizeof($items);
            $data['items'] = $items;
            $this->result = $data;
        } catch (Exception $e) {
            $this->error = "Не удаётся получить список типов доставок!";
        }
    }

    // получить параметры
    private function getConditionsParams($id)
    {
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');
        $u = new DB('shop_delivery_param');
        $u->where('sdp.id_delivery = ?', $id);
        return $u->getList();
    }

    // получить регионы
    private function getConditionsRegions($id, $currency)
    {
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');
        $u = new DB('shop_deliverytype', 'sd');
        $u->select('sd.*, sdr.id id_geo, sdr.id_city, sdr.id_country, sdr.id_region');
        $u->innerJoin('shop_delivery_region sdr', 'sd.id = sdr.id_delivery');
        $u->where('sd.id_parent = ?', $id);
        $result = $u->getList();
        $regions = array();
        foreach ($result as $item) {
            $region = $item;
            $region['idCountryTo'] = !empty($item['idCountry']) ? $item['idCountry'] : null;
            $region['idRegionTo'] = !empty($item['idRegion']) ? $item['idRegion'] : null;
            $region['idCityTo'] = !empty($item['idCity']) ? $item['idCity'] : null;
            $region['period'] = $item['time'];
            $region['currency'] = $currency;
            $region['isActive'] = $item['status'] == 'Y';
            $regions[] = $region;
        }
        return $regions;
    }

    // получить географические регионы
    private function getGeoLocationsRegions($id)
    {
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');
        $u = new DB('shop_delivery_region', 'sdr');
        $u->select('sdr.*');
        $u->where('sdr.id_delivery = ?', $id);
        $result = $u->getList();
        $regions = array();
        foreach ($result as $item) {
            $region = null;
            $region['id'] = $item['id'];
            $region['idCountryTo'] = !empty($item['idCountry']) ? $item['idCountry'] : null;
            $region['idRegionTo'] = !empty($item['idRegion']) ? $item['idRegion'] : null;
            $region['idCityTo'] = !empty($item['idCity']) ? $item['idCity'] : null;
            if ($region['idCountryTo'] || $region['idRegionTo'] || $region['idCityTo'])
                $regions[] = $region;
        }
        return $regions;
    }

    // информация
    public function info($id = NULL)
    {
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');
        try {
            $u = new DB('shop_deliverytype', 'sd');
            $u->select('sd.*, GROUP_CONCAT(DISTINCT(sdp.id_payment) SEPARATOR ";") idsPayments');
            $u->leftJoin('shop_delivery_payment sdp', 'sd.id = sdp.id_delivery');
            $u->where('sd.id = ?', $this->input["id"]);
            $result = $u->getList();
            foreach ($result as $item) {
                $delivery = $item;
                $delivery['idsGroups'] = array();
                $delivery['period'] = $item['time'];
                $delivery['idCityFrom'] = $item['cityFromDelivery'];
                $delivery['isActive'] = $item['status'] == 'Y';
                $delivery['currency'] = $item['curr'];
                $delivery['onePosition'] = $item['forone'] == 'Y';
                $delivery['needAddress'] = $item['needAddress'] == 'Y';
                $idsPaySystems = explode(';', $item['idsPayments']);


                $u1 = new DB('shop_deliverygroup');
                $u1->select('id_group');
                $u1->where('id_type=?', intval($this->input["id"]));
                $idsGroups = $u1->getList();
                $delivery['idsGroups'] = array();
                if (!empty($idsGroups)) {
                    //$idsGroups = explode(';', $item['idsGroups']);
                    foreach($idsGroups as  $gr) {
                        $delivery['idsGroups'][] = intval($gr['idGroup']);
                    }
                }

                foreach ($idsPaySystems as $idGroup)
                    $delivery['idsPaySystems'][] = intval($idGroup);
                $delivery['conditionsParams'] = $this->getConditionsParams($delivery['id']);
                $delivery['conditionsRegions'] = $this->getConditionsRegions($delivery['id'], $delivery['currency']);
                $delivery['geoLocationsRegions'] = $this->getGeoLocationsRegions($delivery['id']);
                $deliveryType = new DeliveryType();
                $delivery['deliveriesTypes'] = $deliveryType->fetch();
                $currency = new Currency();
                $delivery['currencies'] = $currency->fetch();
                $this->result = $delivery;
            }
        } catch (Exception $e) {
            $this->error = "Не удаётся получить информацию о доставке!";
        }
    }

    // получить индекс сортировки
    private function getSortIndex()
    {
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');
        $u = new DB('shop_deliverytype', 'sdt');
        $u->select('MAX(sort) AS sort');
        $u->fetchOne();
        return $u->sort + 1;
    }

    // сохранить систему оплаты
    private function savePaySystem()
    {
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');
        try {
            $idDelivery = $this->input["id"];
            $idsPaySystems = $this->input["idsPaySystems"];
            $idsExist = array();
            foreach ($idsPaySystems as $id)
                if ($id)
                    $idsExist[] = $id;
            $idsExistStr = implode(",", $idsExist);
            $u = new DB('shop_delivery_payment', 'sdp');
            if (empty($idsExist))
                $u->findList("id_delivery = {$idDelivery}")->deleteList();
            else $u->findList("id_delivery = {$idDelivery} AND NOT id_payment IN ({$idsExistStr})")->deleteList();
            $u = new DB('shop_delivery_payment', 'sdp');
            $u->where("id_delivery = ?", $idDelivery);
            $idsExist = array();
            $result = $u->getList();
            foreach ($result as $item)
                $idsExist[] = $item["idPayment"];
            foreach ($idsPaySystems as $id) {
                if ($id && !in_array($id, $idsExist))
                    $data[] = array('id_delivery' => $idDelivery, 'id_payment' => $id);
            }
            if (!empty($data))
                DB::insertList('shop_delivery_payment', $data);
        } catch (Exception $e) {
            $this->error = "Не удаётся сохранить платежные системы доставки!";
            throw new Exception($this->error);
        }
    }

    // сохранить группы
    private function saveGroups()
    {
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');
        $idsGroups = $this->input["idsGroups"];
        $idDelivery = $this->input["id"];
        $u = new DB('shop_deliverygroup', 'sd');
        $u->findList("id_type = $idDelivery")->deleteList();

        if (!empty($idsGroups)) {
            foreach ($idsGroups as $id)
                if ($id)
                    $data[] = array('id_group' => $id, 'id_type' => $idDelivery);
        }
        if (!empty($data))
            DB::insertList('shop_deliverygroup', $data);
    }

    // сохранить параметры
    private function saveConditionsParams()
    {
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');
        $idDelivery = $this->input["id"];
        $conditions = $this->input["conditionsParams"];
        $idsExist = array();
        foreach ($conditions as $condition)
            if (!empty($condition["id"]))
                $idsExist[] = $condition["id"];
        $idsExistStr = implode(",", $idsExist);
        $u = new DB('shop_delivery_param', 'sp');
        if (empty($idsExist))
            $u->where('id_delivery = ?', $idDelivery)->deleteList();
        else $u->where("NOT id IN ({$idsExistStr}) AND id_delivery = ?", $idDelivery)->deleteList();
        foreach ($conditions as $condition) {
            $u = new DB('shop_delivery_param', 'sp');
            $condition["idDelivery"] = $idDelivery;
            $u->setValuesFields($condition);
            $u->save();
        }
    }

    // сохранить регионы
    private function saveConditionsRegions()
    {
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');
        $idDelivery = $this->input["id"];
        $deliveries = $this->input["conditionsRegions"];

        $idsUpdate = array();
        $idsGeoUpdate = array();
        foreach ($deliveries as $delivery) {
            if (!empty($delivery["id"]))
                $idsUpdate[] = $delivery["id"];
            if (!empty($delivery["idGeo"]))
                $idsGeoUpdate[] = $delivery["idGeo"];
        }
        $idsUpdate = implode(',', $idsUpdate);
        $u = new DB('shop_deliverytype', 'sd');
        if (!empty($idsUpdate))
            $u->where('id_parent = ' . $idDelivery . ' AND NOT id IN (' . $idsUpdate . ')')->deleteList();
        else $u->where('id_parent = ' . $idDelivery)->deleteList();

        // вставка новых
        $dataD = array();
        $dataR = array();
        $u = new DB('shop_deliverytype');
        $u->select('MAX(id) max');
        $result = $u->fetchOne();
        $idNew = (int) $result["max"];
        $fl_region = false;
        $fl_type = false;
        foreach ($deliveries as $delivery) {
            if (empty($delivery["id"])) {
                $idNew++;
                $fl_type = true;
                $dataD[] = array('id' => $idNew, 'id_parent' => $idDelivery, 'code' => 'region',
                    'time' => $delivery["period"], 'price' => (real) $delivery["price"],
                    'note' => $delivery["note"], 'max_volume' => $delivery["maxVolume"], 'max_weight' => $delivery["maxWeight"],
                    'status' => $delivery["isActive"] ? 'Y' : 'N');
                if (empty($delivery["idGeo"])) {
                    if (!empty($delivery["idCityTo"])) {
                        $delivery["idCountryTo"] = null;
                        $delivery["idRegionTo"] = null;
                    } elseif (!empty($delivery["idRegionTo"])) {
                        $delivery["idCountryTo"] = null;
                        $delivery["idCityTo"] = null;
                    } elseif (!empty($delivery["idCountryTo"])) {
                        $delivery["idRegionTo"] = null;
                        $delivery["idCityTo"] = null;
                    }
                    $fl_region = true;
                    $dataR[] = array('id_delivery' => $idNew, 'id_country' => $delivery["idCountryTo"],
                        'id_region' => $delivery["idRegionTo"], 'id_city' => $delivery["idCityTo"]);
                }
            }
        }
        if (!empty($dataD))
            DB::insertList('shop_deliverytype', $dataD);
        if (!empty($dataR) && ($fl_region || !$fl_type))
            DB::insertList('shop_delivery_region', $dataR);


        // обновление
        foreach ($deliveries as $delivery) {
            if (!empty($delivery["id"])) {
                $u = new DB('shop_deliverytype');
                $delivery["idParent"] = $idDelivery;
                if (isset($delivery["period"]))
                    $delivery["time"] = $delivery["period"];
                if (isset($delivery["isActive"]))
                    $delivery["status"] = $delivery["isActive"] ? "Y" : "N";
                if (isset($delivery["currency"]))
                    $delivery["curr"] = $delivery["currency"];
                $u->setValuesFields($delivery);
                $u->save();

                if (!empty($delivery["idGeo"])) {
                    if (!empty($delivery["idCityTo"])) {
                        $delivery["idCountryTo"] = null;
                        $delivery["idRegionTo"] = null;
                    } elseif (!empty($delivery['idRegionTo'])) {
                        $delivery["idCountryTo"] = null;
                        $delivery["idCityTo"] = null;
                    } elseif (!empty($delivery["idCountryTo"])) {
                        $delivery["idRegionTo"] = null;
                        $delivery["idCityTo"] = null;
                    }
                    $u = new DB('shop_delivery_region');
                    $delivery["id"] = $delivery["idGeo"];
                    $u->setValuesFields($delivery);
                    $u->save();
                }
            }
        }
    }

    // сохранить геолокацию реионов
    private function saveGeoLocationsRegions()
    {
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');
        $regions = $this->input["geoLocationsRegions"];
        $idDelivery = $this->input["id"];
        $idsUpdate = array();
        foreach ($regions as $region) {
            if (!empty($region["id"]))
                $idsUpdate[] = intval($region["id"]);
        }
        $idsUpdate = implode(',', $idsUpdate);
        $u = new DB('shop_delivery_region', 'sdr');
        $u->where('id_delivery = ?', $idDelivery);
        if (!empty($idsUpdate))
            $u->andWhere('NOT id IN (' . $idsUpdate . ')');
        $u->deleteList();
        $data = array();

        foreach ($regions as $region) {
            if (empty($region["id"])) {
                if (!empty($region["idCityTo"])) {
                    $region["idCountryTo"] = null;
                    $region["idRegionTo"] = null;
                } elseif (!empty($region["idRegionTo"])) {
                    $region["idCountryTo"] = null;
                    $region["idCityTo"] = null;
                } elseif (!empty($region["idCountryTo"])) {
                    $region["idRegionTo"] = null;
                    $region["idCityTo"] = null;
                }
                if ($region["idCountryTo"] || $region["idRegionTo"] || $region["idCityTo"])
                    $data[] = array('id_delivery' => $idDelivery, 'id_country' => $region["idCountryTo"],
                        'id_region' => $region["idRegionTo"], 'id_city' => $region["idCityTo"]);
            }
        }
        if (!empty($data))
            DB::insertList('shop_delivery_region', $data);

        foreach ($regions as $region) {
            if (!empty($region["id"])) {
                if (!empty($region["idCityTo"])) {
                    $region["idCountryTo"] = null;
                    $region["idRegionTo"] = null;
                } elseif (!empty($region["idRegionTo"])) {
                    $region["idCountryTo"] = null;
                    $region["idCityTo"] = null;
                } elseif (!empty($region["idCountryTo"])) {
                    $region["idRegionTo"] = null;
                    $region["idCityTo"] = null;
                }
                $regionl = array();
                $u = new DB('shop_delivery_region');
                $regionl["id_country"] = $region['idCountryTo'];
                $regionl["id_region"] = $region['idRegionTo'];
                $regionl["id_city"] = $region['idCityTo'];
                $u->setValuesFields($regionl);
                $u->where('id=?', $region["id"]);
                $u->save();
            }
        }
    }

    // правильные значения перед сохранением
    protected function correctValuesBeforeSave()
    {
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');
        $isNew = empty($this->input["id"]);
        if ($isNew)
            $this->input["sortIndex"] = $this->getSortIndex();
        if (isset($this->input["period"]))
            $this->input["time"] = $this->input["period"];
        if (isset($this->input["sortIndex"]))
            $this->input["sort"] = $this->input["sortIndex"];
        if (isset($this->input["isActive"]))
            $this->input["status"] = $this->input["isActive"] ? "Y" : "N";
        if (isset($this->input["currency"]))
            $this->input["curr"] = $this->input["currency"];
        if (isset($this->input["idCityFrom"]))
            $this->input["city_from_delivery"] = $this->input["idCityFrom"];
        if (isset($this->input["onePosition"]))
            $this->input["forone"] = $this->input["onePosition"] ? "Y" : "N";
    }

    // сохранить добавленную информацию
    protected function saveAddInfo()
    {
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');
        $this->saveGroups();
        $this->savePaySystem();
        $this->saveConditionsParams();
        $this->saveConditionsRegions();
        $this->saveGeoLocationsRegions();
        return true;
    }
}