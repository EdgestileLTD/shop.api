<?php

namespace SE\Shop;

use SE\DB as seTable;
use SE\Exception;

// регионы доставки
class DeliveryRegion extends Base
{
    protected $tableName = "shop_delivery_region";

    // получить
    public function fetch()
    {
        try {
            $u = new seTable('shop_deliverytype', 'sdt');
            $u->select("sdt.*, GROUP_CONCAT(sdr.id_country) id_country, 
                GROUP_CONCAT(sdr.id_region) id_region,
                GROUP_CONCAT(sdr.id_city) id_city");
            $u->leftJoin('shop_delivery_region sdr', 'sdt.id = sdr.id_delivery');
            $u->where('sdt.id_parent = ?', $this->input["idDelivery"]);
            $u->orderBy('sdt.sort', false);
            $u->groupBy('sdt.id');
            $objects = $u->getList();
            foreach ($objects as $item) {
                $delivery = null;
                $delivery['id'] = $item['id'];
                $delivery['regions'] = array();
                $delivery['regions']['idCountry'] = explode(',', $item['id_country']);
                $delivery['regions']['idRegion'] = explode(',', $item['id_region']);
                $delivery['regions']['idCity'] = explode(',', $item['id_city']);
                $delivery['price'] = (float)$item['price'];
                $delivery['volumeMax'] = (float)$item['max_volume'];
                $delivery['weightMax'] = $item['max_weight'];
                $delivery['period'] = $item['time'];
                $delivery['addr'] = $item['note'];
                $delivery['isActive'] = ($item['status'] == 'Y');
                $items[] = $delivery;
            }

            $this->result['count'] = sizeof($items);
            $this->result['items'] = $items;
        } catch (Exception $e) {
            $this->error = "Не удаётся получить список регионов доставок!";
        }
    }

    // информация о регионе доставки
    public function info($id = NULL)
    {
        try {
            $u = new seTable('shop_deliverytype', 'sdt');
            $u->select("sdt.*, GROUP_CONCAT(sdr.id_country) AS id_country,
                GROUP_CONCAT(sdr.id_region) AS id_region,
                GROUP_CONCAT(sdr.id_city) AS id_city");
            $u->leftJoin('shop_delivery_region sdr', 'sdt.id=sdr.id_delivery');
            $u->where('sdt.id = ?', $this->input["id"]);
            $u->groupBy('sdt.id');
            $objects = $u->getList();
            foreach ($objects as $item) {
                $delivery = null;
                $delivery['id'] = $item['id'];
                $delivery['regions'] = array();
                $delivery['regions']['idCountry'] = explode(',', $item['id_country']);
                $delivery['regions']['idRegion'] = explode(',', $item['id_region']);
                $delivery['regions']['idCity'] = explode(',', $item['id_city']);
                $delivery['price'] = (float)$item['price'];
                $delivery['volumeMax'] = (float)$item['max_volume'];
                $delivery['weightMax'] = $item['max_weight'];
                $delivery['period'] = $item['time'];
                $delivery['addr'] = $item['note'];
                $delivery['isActive'] = ($item['status'] == 'Y');
                $items[] = $delivery;
            }
            $this->result['count'] = sizeof($items);
            $this->result['items'] = $items;
        } catch (Exception $e) {
            $this->error = "Не удаётся получить информацию о регионе доставки!";
        }
    }

}