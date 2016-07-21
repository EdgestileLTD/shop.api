<?php

namespace SE\Shop;

use SE\DB as seTable;
use SE\Exception;

class Delivery extends Base
{
    protected $tableName = "shop_deliverytype";

    public function fetch()
    {
        try {
            $u = new seTable('shop_deliverytype', 'sd');
            $u->select('sd.*');
            $u->where('sd.id_parent IS NULL');
            $u->orderby('sort', false);

            $objects = $u->getList();
            foreach ($objects as $item) {
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
            $this->error = "Не удаётся получить список типов дооставок!";
        }
    }

    private function getConditionsParams($id)
    {
        $u = new seTable('shop_delivery_param', 'sdp');
        $u->select('sdp.*');
        $u->where('sdp.id_delivery = ?', $id);
        return $u->getList();
    }

    private function getConditionsRegions($id, $currency)
    {
        $u = new seTable('shop_deliverytype', 'sd');
        $u->select('sd.*, sdr.id idGeo, sdr.id_city, sdr.id_country, sdr.id_region');
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

    private function getGeoLocationsRegions($id)
    {
        $u = new seTable('shop_delivery_region', 'sdr');
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

    public function info()
    {
        try {
            $u = new seTable('shop_deliverytype', 'sd');
            $u->select('sd.*, GROUP_CONCAT(DISTINCT(sdp.id_payment) SEPARATOR ";") idsPayments,
                        GROUP_CONCAT(DISTINCT(sdg.id_group) SEPARATOR ";") idsGroups');
            $u->leftJoin('shop_delivery_payment sdp', 'sd.id = sdp.id_delivery');
            $u->leftJoin('shop_deliverygroup sdg', 'sd.id = id_type');
            $u->where('sd.id = ?', $this->input["id"]);
            $result = $u->getList();
            foreach ($result as $item) {
                $delivery = $item;
                $delivery['period'] = $item['time'];
                $delivery['idCityFrom'] = $item['cityFromDelivery'];
                $delivery['isActive'] = $item['status'] == 'Y';
                $delivery['currency'] = $item['curr'];
                $delivery['onePosition'] = $item['forone'] == 'Y';
                $delivery['needAddress'] = $item['needAddress'] == 'Y';
                $idsPaySystems = explode(';', $item['idsPayments']);
                if (trim($item['idsGroups']))
                    $delivery['idsGroups'] = explode(';', $item['idsGroups']);
                foreach ($idsPaySystems as $idGroup)
                    $delivery['idsPaySystems'][] = $idGroup;
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

    private function getSortIndex()
    {
        $u = new seTable('shop_deliverytype', 'sdt');
        $u->select('MAX(sort) AS sort');
        $u->fetchOne();
        return $u->sort + 1;
    }

    private function savePaySystem()
    {
        $idDelivery = $this->input["id"];
        $idsPaySystems = $this->input["idsPaySystems"];
        $u = new seTable('shop_delivery_payment', 'sdp');
        $u->findList("id_delivery = {$idDelivery}")->deletelist();

        if (!empty($idsPaySystems)) {
            foreach ($idsPaySystems as $id) {
                $u = new seTable('shop_delivery_payment', 'sdp');
                $u->idDelivery = $idDelivery;
                $u->idPayment = $id;
                $u->save();
            }
        }
    }

    private function saveGroups()
    {
        $idsGroups = $this->input["idsGroups"];
        $idDelivery = $this->input["id"];
        $u = new seTable('shop_deliverygroup', 'sd');
        $u->findList("id_type = $idDelivery")->deleteList();

        if (!empty($idsGroups)) {
            foreach ($idsGroups as $id)
                if ($id)
                    $data[] = array('id_group' => $id, 'id_type' => $idDelivery);
        }
        if (!empty($data))
            seTable::insertList('shop_deliverygroup', $data);
    }

    private function saveConditionsParams()
    {
        $conditions = $this->input["id"];
        $idDelivery = $this->input["conditionsParams"];
        $u = new seTable('shop_delivery_param', 'sp');
        $u->where('id_delivery = ?', $idDelivery)->deleteList();
        foreach ($conditions as $c)
            $data[] = array('id_delivery' => $idDelivery, 'type_param' => $c["typeParam"], 'price' => $c["price"],
                'min_value' => $c["minValue"], 'max_value' => $c["maxValue"], 'priority' => $c["priority"], 'operation' =>
                    $c["operation"], "type_price" => $c["typePrice"]);
        if (!empty($data))
            seTable::insertList('shop_delivery_param', $data);
    }

    private function saveConditionsRegions()
    {
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
        $u = new seTable('shop_deliverytype', 'sd');
        if (!empty($idsUpdate))
            $u->where('id_parent = ' . $idDelivery . ' AND NOT id IN (' . $idsUpdate . ')')->deleteList();
        else $u->where('id_parent = ' . $idDelivery)->deleteList();

        // вставка новых
        $dataD = array();
        $dataR = array();
        $u = new seTable('shop_deliverytype');
        $u->select('MAX(id) AS maxId');
        $u->fetchOne();
        $idNew = (int)$u->maxId;
        foreach ($deliveries as $delivery) {
            if (empty($delivery["id"])) {
                $idNew++;
                $dataD[] = array('id' => $idNew, 'id_parent' => $idDelivery, 'code' => 'region',
                    'time' => $delivery["period"], 'price' => $delivery["price"],
                    'curr' => $delivery["currency"], 'note' => $delivery["note"],
                    'max_volume' => $delivery["maxVolume"], 'max_weight' => $delivery["maxWeight"],
                    'status' => $delivery["isActive"] ? 'Y' : 'N');
                if (empty($delivery["idGeo"])) {
                    if (!empty($delivery["idCityTo"])) {
                        $delivery["idCountryTo"] = null;
                        $delivery["idRegionTo"] = null;
                    } elseif (!empty($delivery["idRegionTo"])) {
                        $delivery["idCountryTo"] = null;
                        $delivery["idCityTo"] = 'null';
                    } elseif (!empty($delivery["idCountryTo"])) {
                        $delivery["idRegionTo"] = null;
                        $delivery["idCityTo"] = null;
                    }
                    $dataR[] = array('id_delivery' => $idNew, 'id_country' => $delivery["idCountryTo"],
                        'id_region' => $delivery["idRegionTo"], 'id_city' => $delivery["idCityTo"]);
                }
            }
        }
        if (!empty($dataD))
            seTable::insertList('shop_deliverytype', $dataD);
        if (!empty($dataR)) {
            seTable::insertList('shop_delivery_region', $dataR);
        }

        // обновление
        foreach ($deliveries as $delivery) {
            if (!empty($delivery["id"])) {
                $u = new seTable('shop_deliverytype');
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
                    $u = new seTable('shop_delivery_region');
                    $delivery["id"] = $delivery["idGeo"];
                    $u->setValuesFields($delivery);
                    $u->save();
                }
            }
        }
    }

    private function saveGeoLocationsRegions()
    {
        $regions = $this->input["geoLocationsRegions"];
        $idDelivery = $this->input["id"];
        $idsUpdate = array();
        foreach ($regions as $region)
            if (!empty($region["id"]))
                $idsUpdate[] = $region["id"];
        $idsUpdate = implode(',', $idsUpdate);
        $u = new seTable('shop_delivery_region', 'sdr');
        $u->where('id_delivery = ?', $idDelivery);
        if (!empty($idsUpdate))
            $u->andWhere('NOT id IN (' . $idsUpdate . ')');
        $u->deleteList();

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
            seTable::insertList('shop_delivery_region', $data);

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
                $u = new seTable('shop_delivery_region');
                $region["idCountry"] = $region->idCountryTo;
                $region["idRegion"] = $region->idRegionTo;
                $region["idCity"] = $region->idCityTo;
                $u->setValuesFields($region);
                $u->save();
            }
        }
    }

    protected function correctValuesBeforeSave()
    {
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

    protected function saveAddInfo()
    {
        $this->saveGroups();
        $this->savePaySystem();
//        $this->saveConditionsParams();
//        $this->saveConditionsRegions();
        $this->saveGeoLocationsRegions();
        return true;
    }
}