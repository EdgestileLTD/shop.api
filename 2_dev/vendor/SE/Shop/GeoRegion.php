<?php

namespace SE\Shop;

// геолокация-регион
class GeoRegion extends Base
{
    // получить
    public function fetch()
    {
        $ids = array();
        if (empty($this->input["ids"]) && !empty($this->input["id"]))
            $ids[] = $this->input["id"];
        else $ids = $this->input["ids"];
        $data = array('action' => 'region',
            'idCountry' => $this->input["idCountry"],
            'search' => $this->input["searchText"],
            'ids' => $ids);
        $data = http_build_query($data);
        $url = "https://api.siteedit.ru/api/geo/?".$data;
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        $this->result = json_decode(curl_exec($curl), true);
    }
}