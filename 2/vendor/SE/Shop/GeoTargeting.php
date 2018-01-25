<?php

namespace SE\Shop;

use SE\Exception;
use SE\DB;

// геотаргетинг
class GeoTargeting extends Base
{
    protected $tableName = "shop_contacts";
    protected $sortBy = "sort";
    protected $sortOrder = "asc";

    // получить настройки
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

    // получить информацию по настройкам
    protected function getSettingsInfo()
    {
        return $this->getSettingsFetch();
    }

    // правильные значения перед извлечением
    protected function correctItemsBeforeFetch($items = array())
    {
        $idsCities = array();
        foreach ($items as $item)
            $idsCities[] = $item["idCity"];
        if ($idsCities) {
            $cities = $this->getCitiesByIds($idsCities);
            foreach ($items as &$item)
                $item["city"] = $cities[$item["idCity"]];
        }
        return $items;
    }

    // получить переменные
    private function getVariables()
    {
        $this->createDbGeoVariables();
        $sv = new DB('shop_variables', 'sv');
        $sv->select('sgv.id, sv.id AS id_variable, sv.name, sgv.value');
        $sv->leftJoin('shop_geo_variables sgv', 'sv.id=sgv.id_variable AND sgv.id_contact='.intval($this->input['id']));
        return $sv->getList();
    }

    // добавить полученную информацию
    protected function getAddInfo()
    {
        $result = array();
        if (!empty($this->result["idCity"])) {
            $cities = $this->getCitiesByIds(array($this->result["idCity"]));
            if (count($cities))
                $result["city"] = $cities[$this->result["idCity"]];
        }
        $result["variables"] = $this->getVariables();
        return $result;
    }

    // создать Db гео-переменные
    private function createDbGeoVariables()
    {
        DB::query("CREATE TABLE IF NOT EXISTS `shop_geo_variables` (
          `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
          `id_contact` int(10) UNSIGNED NOT NULL,
          `id_variable` int(10) UNSIGNED NOT NULL,
          `value` varchar(255) DEFAULT NULL,
          `updated_at` timestamp NULL DEFAULT NULL,
          `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          KEY `id_variables` (`id_variable`),
          KEY `id_contacts` (`id_contact`),
          CONSTRAINT `shop_geo_variables_ibfk_1` FOREIGN KEY (`id_contact`) REFERENCES `shop_contacts` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
          CONSTRAINT `shop_geo_variables_ibfk_2` FOREIGN KEY (`id_variable`) REFERENCES `shop_variables` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
          ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
       ");
    }

    // сохранить добавленную информацию
    protected function saveAddInfo()
    {
        return $this->saveContactGeo() && $this->saveVariables();
    }

    // сохранить переменные
    private function saveVariables()
    {
        $data = $this->input;
        unset($data["ids"]);
        try {
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


    // сохранить гео-контакт
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

    // получить IDs городов
    private function getCitiesByIds($ids = array())
    {
        $data = array('action' => 'city',
            'ids' => $ids);
        $data = http_build_query($data);
        $url = "https://api.siteedit.ru/api/geo/?" . $data;
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        $data = json_decode(curl_exec($curl), true);
        if ($data["items"]) {
            $result = array();
            foreach ($data["items"] as $item)
                $result[$item["id"]] = $item["name"];
            return $result;
        }
        return array();
    }

    // сохранить
    public function save()
    {
        $t = new DB('shop_contacts');
        $t->add_field('url', 'varchar(255)');
        parent::save();
    }
}