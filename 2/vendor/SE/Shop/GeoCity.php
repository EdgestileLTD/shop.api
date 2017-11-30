<?php

namespace SE\Shop;

// геолокация-город
class GeoCity extends Base
{
    // получить
    public function fetch()
    {
        $idCountry = $this->input["idCountry"] ? $this->input["idCountry"] : $_GET['idCountry'];
        $idRegion = $this->input["idRegion"] ? $this->input["idRegion"] : $_GET['idRegion'];
        $search = ($this->input["searchText"]) ? $this->input["searchText"] : $_GET['search'];
        $ids = array();
        if (empty($this->input["ids"]) && !empty($this->input["id"]))
            $ids[] = $this->input["id"];
        else $ids = $this->input["ids"];
        if (!$idCountry && !$idRegion && !$search && !$ids) {
            $this->result["items"] = array();
            $this->result["count"] = 0;
            return;
        }
        $data = array('action' => 'city',
            'idCountry' => $idCountry,
            'idRegion' => $idRegion,
            'search' => $search,
            'ids' => $ids);
        $data = http_build_query($data);
        $url = "https://api.siteedit.ru/api/geo/?".$data;
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        $this->result = json_decode(curl_exec($curl), true);
    }
}