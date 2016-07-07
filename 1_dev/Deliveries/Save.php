<?php

    $id = $json->id;
    $code = $json->code;
    $name = $json->name;
    $note = $json->note;
    $period = $json->period;
    $price = $json->price;
    $cityFrom = $json->cityFrom;
    $idCityFrom = $json->idCityFrom;
    $week = $json->week;
    $onePosition = $json->onePosition;
    $currency = $json->currency;
    $maxVolume = $json->maxVolume;
    $maxWeight = $json->maxWeight;
    $isActive = $json->isActive;
    $needAddress = $json->needAddress;
    $idsPaySystems = $json->idsPaySystems;
    $idsGroups = $json->idsGroups;
    $conditionsParams = $json->conditionsParams;
    $conditionsRegions = $json->conditionsRegions;
    $geoLocationsRegions = $json->geoLocationsRegions;
    if (!$currency)
        $currency = 'RUB';

    function getSortIndex()
    {
        $u = new seTable('shop_deliverytype','sdt');
        $u->select('MAX(sort) AS sort');
        $u->fetchOne();
        return $u->sort + 1;
    }

    function savePaySystem($idpaysystems, $idDelivery){
        $u = new seTable('shop_delivery_payment','sdp');
        $u->findlist("id_delivery=$idDelivery")->deletelist();

        if (!empty($idpaysystems)) {
            foreach($idpaysystems as $id) {
                $u = new seTable('shop_delivery_payment','sdp');
                $u->id_delivery = $idDelivery;
                $u->id_payment = $id;
                $u->save();
            }
        }
    }

    function saveGroups($idsGroups, $idDelivery) {

        $u = new seTable('shop_deliverygroup','sd');
        $u->findlist("id_type=$idDelivery")->deletelist();

        if (!empty($idsGroups)) {
            foreach($idsGroups as $id)
                $data[] = array('id_group' => $id, 'id_type' => $idDelivery);
        }
        if (!empty($data))
            se_db_InsertList('shop_deliverygroup', $data);
    }

    function saveConditionsParams($conditions, $idDelivery) {
        $u = new seTable('shop_delivery_param','sp');
        $u->where('id_delivery=(?)', $idDelivery)->deletelist();

        foreach ($conditions as $c)
            $data[] = array('id_delivery' => $idDelivery, 'type_param' => $c->typeParam, 'price' => $c->price,
                'min_value' => $c->minValue, 'max_value' => $c->maxValue, 'priority' => $c->priority, 'operation' =>
                    $c->operation, "type_price" => $c->typePrice);
        if (!empty($data))
            se_db_InsertList('shop_delivery_param', $data);
    }

    function saveConditionsRegions($deliveries, $idDelivery) {
        GLOBAL $json;

        $idsUpdate = array();
        $idsGeoUpdate = array();
        foreach ($deliveries as $delivery) {
            if (!empty($delivery->id))
                $idsUpdate[] = $delivery->id;
            if (!empty($delivery->idGeo))
                $idsGeoUpdate[] = $delivery->idGeo;
        }
        $idsUpdate = implode(',', $idsUpdate);
        $idsGeoUpdate = implode(',', $idsGeoUpdate);
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
        $idNew = (int) $u->maxId;
        foreach($deliveries as $delivery) {
            if (empty($delivery->id)) {
                $idNew++;
                $dataD[] = array('id' => $idNew, 'id_parent' => $idDelivery, 'code' => 'region',
                    'time' => $delivery->period, 'price' => $delivery->price, 'curr' => $delivery->currency, 'note' => $delivery->note,
                    'max_volume' => $delivery->maxVolume, 'max_weight' => $delivery->maxWeight,
                    'status' => $delivery->isActive ? 'Y' : 'N');
                if (empty($delivery->idGeo)) {
                    if (!empty($delivery->idCityTo)) {
                        $delivery->idCountryTo = 'null';
                        $delivery->idRegionTo = 'null';
                    } elseif (!empty($delivery->idRegionTo)) {
                        $delivery->idCountryTo = 'null';
                        $delivery->idCityTo = 'null';
                    } elseif (!empty($delivery->idCountryTo)) {
                        $delivery->idRegionTo = 'null';
                        $delivery->idCityTo = 'null';
                    }
                    $dataR[] = array('id_delivery' => $idNew, 'id_country' => $delivery->idCountryTo,
                        'id_region' => $delivery->idRegionTo, 'id_city' => $delivery->idCityTo);
                }
            }
        }
        if (!empty($dataD))
            se_db_InsertList('shop_deliverytype', $dataD);
        if (!empty($dataR)){
            se_db_InsertList('shop_delivery_region', $dataR);
        }

        // обновление
        foreach($deliveries as $delivery) {
            if (!empty($delivery->id)) {
                $u = new seTable('shop_deliverytype');
                setField(0, $u, $idDelivery, 'id_parent');
                setField(0, $u, $delivery->period, 'time');
                setField(0, $u, $delivery->price, 'price');
                setField(0, $u, $delivery->note, 'note');
                setField(0, $u, ($delivery->isActive ? 'Y' : 'N'), 'status');
                setField(0, $u, $delivery->currency, 'curr');
                setField(0, $u, $delivery->maxVolume, 'max_volume');
                setField(0, $u, $delivery->maxWeight, 'max_weight');
                $u->where('id=?', $delivery->id);
                $u->save();

                if (!empty($delivery->idGeo)) {
                    if (!empty($delivery->idCityTo)) {
                        $delivery->idCountryTo = null;
                        $delivery->idRegionTo = null;
                    } elseif (!empty($delivery->idRegionTo)) {
                        $delivery->idCountryTo = null;
                        $delivery->idCityTo = null;
                    } elseif (!empty($delivery->idCountryTo)) {
                        $delivery->idRegionTo = null;
                        $delivery->idCityTo = null;
                    }
                    $u = new seTable('shop_delivery_region');
                    setField(0, $u, $delivery->idCountryTo, 'id_country');
                    setField(0, $u, $delivery->idRegionTo, 'id_region');
                    setField(0, $u, $delivery->idCityTo, 'id_city');
                    $u->where('id=?', $delivery->idGeo);
                    $u->save();
                }
            }
        }
    }

    function saveGeoLocationsRegions($regions, $idDelivery) {

        $idsUpdate = array();
        foreach ($regions as $region)
            if (!empty($region->id))
                $idsUpdate[] = $region->id;
        $idsUpdate = implode(',', $idsUpdate);
        $u = new seTable('shop_delivery_region', 'sdr');
        $u->where('id_delivery = ?', $idDelivery);
        if (!empty($idsUpdate))
            $u->andWhere('NOT id IN (' . $idsUpdate . ')');
        $u->deleteList();

        foreach($regions as $region) {
            if (empty($region->id)) {
                if (!empty($region->idCityTo)) {
                    $region->idCountryTo = 'null';
                    $region->idRegionTo = 'null';
                } elseif (!empty($region->idRegionTo)) {
                    $region->idCountryTo = 'null';
                    $region->idCityTo = 'null';
                } elseif (!empty($region->idCountryTo)) {
                    $region->idRegionTo = 'null';
                    $region->idCityTo = 'null';
                }
                $data[] = array('id_delivery' => $idDelivery, 'id_country' => $region->idCountryTo,
                    'id_region' => $region->idRegionTo, 'id_city' => $region->idCityTo);
            }
        }
        if (!empty($data))
            se_db_InsertList('shop_delivery_region', $data);

        foreach($regions as $region) {
            if (!empty($region->id)) {
                if (!empty($region->idCityTo)) {
                    $region->idCountryTo = null;
                    $region->idRegionTo = null;
                } elseif (!empty($region->idRegionTo)) {
                    $region->idCountryTo = null;
                    $region->idCityTo = null;
                } elseif (!empty($region->idCountryTo)) {
                    $region->idRegionTo = null;
                    $region->idCityTo = null;
                }
                $u = new seTable('shop_delivery_region');
                setField(0, $u, $region->idCountryTo, 'id_country');
                setField(0, $u, $region->idRegionTo, 'id_region');
                setField(0, $u, $region->idCityTo, 'id_city');
                $u->where('id=?', $region->id);
                $u->save();
            }
        }
    }

    if (!empty($name)) {
        $u = new seTable('shop_deliverytype');
        $isNew = (empty($json->id));
        if ($isNew)
            $json->sortIndex = getSortIndex();
        $isUpdated = false;
        $isUpdated |= setField($isNew, $u, $json->code, 'code');
        $isUpdated |= setField($isNew, $u, $json->name, 'name');
        $isUpdated |= setField($isNew, $u, $json->period, 'time');
        $isUpdated |= setField($isNew, $u, $json->price, 'price');
        $isUpdated |= setField($isNew, $u, $json->note, 'note');
        $isUpdated |= setField($isNew, $u, $json->sortIndex, 'sort');
        if (isset($json->isActive))
            $isUpdated |= setField($isNew, $u, (($json->isActive) ? 'Y' : 'N'), 'status');
        $isUpdated |= setField($isNew, $u, $json->week, 'week');
        $isUpdated |= setField($isNew, $u, $json->currency, 'curr');
        $isUpdated |= setField($isNew, $u, $json->idCityFrom, 'city_from_delivery', 'int(10) default NULL');
        $isUpdated |= setField($isNew, $u, $json->maxVolume, 'max_volume');
        $isUpdated |= setField($isNew, $u, $json->maxWeight, 'max_weight');
        $isUpdated |= setField($isNew, $u, $json->sort, 'sort');
        if (isset($json->onePosition))
            $isUpdated |= setField($isNew, $u, (($json->onePosition) ? 'Y' : 'N'), 'forone');
        if (isset($json->needAddress))
            $isUpdated |= setField($isNew, $u, (($json->needAddress) ? 'Y' : 'N'), 'need_address');
        if ($isUpdated){
            if (!empty($id)){
                $u->where('id=?', $id);
                $u->save();
            } else
                $id = $u->save();
        }
    } else {
        $id = 0;
    }

    if ($id && !se_db_error()) {
        saveGroups($idsGroups, $id);
        if (isset($idsPaySystems))
            savePaySystem($idsPaySystems, $id);
        if (isset($conditionsParams))
            saveConditionsParams($conditionsParams, $id);
        if (isset($conditionsRegions))
            saveConditionsRegions($conditionsRegions, $id);
        if (isset($geoLocationsRegions))
            saveGeoLocationsRegions($geoLocationsRegions, $id);
    }

    $data['id'] = $id;
    $status = array();
    if (!se_db_error()) {
        $status['status'] = 'ok';
        $status['data'] = $data;
    } else {
        $status['status'] = 'error';
        $status['errortext'] = se_db_error();
    }

    outputData($status);
