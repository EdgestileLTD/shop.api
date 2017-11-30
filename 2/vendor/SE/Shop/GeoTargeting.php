<?php

namespace SE\Shop;

use SE\Exception;
use SE\DB;

class GeoTargeting extends Base
{
    protected $tableName = "shop_contacts";
    protected $sortBy = "sort";
    protected $sortOrder = "asc";
    protected $limit = 1000;

    protected function getSettingsFetch()
    {
        $this->limit = 1000;
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

    private function getVariables()
    {
        $sv = new DB('shop_variables', 'sv');
        $sv->select('sgv.id, sv.id AS id_variable, sv.name, sgv.value');
        $sv->leftJoin('shop_geo_variables sgv', 'sv.id=sgv.id_variable AND sgv.id_contact='.intval($this->input['id']));
        return $sv->getList();
    }

    protected function getAddInfo()
    {
        $result = [];
        if (!empty($this->result["idCity"])) {
            $cities = $this->getCitiesByIds(array($this->result["idCity"]));
            if (count($cities))
                $result["city"] = $cities[$this->result["idCity"]];
        }
        $result["variables"] = $this->getVariables();
        return $result;
    }

    protected function saveAddInfo()
    {
        return $this->saveContactGeo() && $this->saveVariables();
    }

    private function saveVariables()
    {
        $data = $this->input;
        unset($data["ids"]);
        try {
            $u = new DB('shop_geo_variables');
            $fld = $u->getField('value');
            if ($fld['type'] !== 'text') {
                DB::exec('ALTER TABLE `shop_geo_variables` CHANGE `value` `value` TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL;');
            }
            foreach($data['variables'] as $variable) {
                $variable["idContact"] = $data["id"];
                $t = new DB('shop_geo_variables');
                $t->setValuesFields($variable);
                $t->save();
            }
            return true;
        } catch (Exception $e) {
            $this->error = "Не удаётся сохранить переменные геотаргетинга!";
        }
        return false;
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

    public function save()
    {
        $t = new DB('shop_contacts');
        $t->add_field('url', 'varchar(255)');
        parent::save();
    }
}