<?php

namespace SE\Shop;

// геолокация
class Geo extends Base
{
    // получить
    public function fetch()
    {
        $idCountry = $this->input["idCountry"];
        $idRegion = $this->input["idRegion"];
        $idCity = $this->input["idCity"];
        $search = $this->input["searchText"];
        if (!$idCountry && !$idRegion && !$idCity && !$search) {
            $this->result["items"] = array();
            $this->result["count"] = 0;
            return;
        }
        $data = array('action' => 'geo',
            'idCountry' => $idCountry,
            'idRegion' => $idRegion,
            'idCity' => $idCity,
            'search' => $search);
        $data = http_build_query($data);
        $url = "https://api.siteedit.ru/api/geo/?".$data;
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        $this->result = json_decode(curl_exec($curl), true);

    }
}