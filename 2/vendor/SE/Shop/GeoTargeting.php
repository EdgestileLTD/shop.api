<?php

namespace SE\Shop;

use SE\Exception;
use SE\DB;

class GeoTargeting extends Base
{
    protected $tableName = "shop_contacts";
    protected $sortBy = "sort";
    protected $sortOrder = "asc";

    protected function getSettingsFetch()
    {
        return [
            "select" => "sc.*, scg.id id_geo, scg.id_contact id_contact, scg.id_city id_city",
            "joins" => [
                [
                    "type" => "left",
                    "table" => 'shop_contacts_geo scg',
                    "condition" => 'scg.id_contact = sc.id'
                ]
            ]
        ];
    }

    protected function getSettingsInfo()
    {
        return $this->getSettingsFetch();
    }

    protected function correctValuesBeforeFetch($items = [])
    {
        $idsCities = [];
        foreach ($items as $item)
            $idsCities[] = $item["idCity"];
        if ($idsCities) {
            $cities = $this->getCitiesByIds($idsCities);
            foreach ($items as &$item)
                $item["city"] = $cities[$item["idCity"]];
        }
        return $items;
    }

    protected function getAddInfo()
    {
        $result = [];
        if (!empty($this->result["idCity"])) {
            $cities = $this->getCitiesByIds(array($this->result["idCity"]));
            if (count($cities))
                $result["city"] = $cities[$this->result["idCity"]];
        }
        return $result;
    }

    protected function saveAddInfo()
    {
        return $this->saveContactGeo();
    }

    private function saveContactGeo()
    {
        $data = $this->input;
        $data["idContact"] = $data["id"];
        unset($data["ids"]);
        if (!empty($data["idGeo"]))
            $data["id"] = $data["idGeo"];
        else unset($data["id"]);
        try {
            $t = new DB('shop_contacts_geo');
            $t->setValuesFields($data);
            $t->save();
            return true;
        } catch (Exception $e) {
            $this->error = "Не удаётся сохранить город для контакта геотаргетинга!";
        }
        return false;
    }

    private function getCitiesByIds($ids = [])
    {
        $data = array('action' => 'city',
            'ids' => $ids);
        $data = http_build_query($data);
        $url = "https://api.siteedit.ru/api/geo/?" . $data;
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        $data = json_decode(curl_exec($curl), true);
        if ($data["items"]) {
            $result = [];
            foreach ($data["items"] as $item)
                $result[$item["id"]] = $item["name"];
            return $result;
        }
        return [];
    }
}