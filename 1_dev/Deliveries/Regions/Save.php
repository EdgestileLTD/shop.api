<?php
    $ids = array();
    if (empty($json->ids) && !empty($json->id))
        $ids[] = $json->id;
    else $ids = $json->ids;
    $isNew = empty($ids);
    if (!$isNew)
        $idsStr = implode(",", $ids);
        

    function setRegions($id_delivery, $regions){
        $id_delivery = intval($id_delivery);
        se_db_query("CREATE TABLE IF NOT EXISTS `shop_delivery_region` (
        `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
        `id_delivery` int(10) unsigned NOT NULL,
        `id_country` int(11) DEFAULT NULL,
        `id_region` int(11) DEFAULT NULL,
        `id_city` int(11) DEFAULT NULL,
        `updated_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00' ON UPDATE CURRENT_TIMESTAMP,
        `created_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
        PRIMARY KEY (`id`),
        KEY `id_delivery` (`id_delivery`),
        CONSTRAINT `shop_delivery_region_ibfk_1` FOREIGN KEY (`id_delivery`) REFERENCES `shop_deliverytype` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;");
        se_db_delete('shop_delivery_region', "id_delivery={$id_delivery}");
        $data = array();
        $max_count = 0;
        if (count($regions->idCountry) > $max_count) $max_count = count($regions->idCountry);
        if (count($regions->idRegion) > $max_count) $max_count = count($regions->idRegion);
        if (count($regions->idCity) > $max_count) $max_count = count($regions->idCity);
        for($i=0; $i<$max_count; $i++){
           $idCountry = (!empty($regions->idCountry[$i]))? $regions->idCountry[$i] : 'null';
           $idRegion = (!empty($regions->idRegion[$i]))? $regions->idRegion[$i] : 'null';
           $idCity = (!empty($regions->idCity[$i]))? $regions->idCity[$i] : 'null';
           $data[] = array('id_delivery'=>$id_delivery, 'id_country'=>$idCountry, 'id_region'=>$idRegion, 'id_city'=>$idCity);
        }
        if (!empty($data)){
           se_db_InsertList('shop_delivery_region', $data);
        }
    }

    $status = array();
    if (empty($json->idDelivery)){
        $status['status'] = 'error';
        $status['error'] = 'Not id delivery';
    } else {
        if ($isNew || !empty($ids)) {
            $u = new seTable('shop_deliverytype');
            $u->id_parent = $json->idDelivery;
            $isUpdated = false;
            $isUpdated |= setField($isNew, $u, $json->price, 'price', 'double(10,2) default 0.00');
            $isUpdated |= setField($isNew, $u, $json->volumeMax, 'max_volume', 'double(10,3) default 0.000');
            $isUpdated |= setField($isNew, $u, $json->weightMax, 'max_weight', 'double(10,3) default 0.000');
            $isUpdated |= setField($isNew, $u, $json->addr, 'note', 'text default NULL');
            $isUpdated |= setField($isNew, $u, $json->period, 'time', "int(6) default 1");
            $stats = ($json->isActive) ? 'Y' : 'N';
            $isUpdated |= setField($isNew, $u, $stats, 'status');

            if ($isUpdated){
                if (!empty($idsStr))
                    $u->where('id in (?)', $idsStr);
                $idv = $u->save();
                if ($isNew)
                    $ids[] = $idv;
            }
            if (!empty($ids))
            foreach($ids as $idd){
               setRegions($idd, $json->regions);
            }
        }

        $data['id'] = $ids[0];
        if (!se_db_error()) {
            $status['status'] = 'ok';
            $status['data'] = $data;
        } else {
            $status['status'] = 'error';
            $status['error'] = se_db_error();
    }
}
    outputData($status);
