<?php

namespace SE\Shop;

// геолокация-страна
class GeoCountry extends Base
{
    // получить
    public function fetch()
    {
        $search = $this->input["searchText"];
        $ids = array();
        if (empty($this->input["ids"]) && !empty($this->input["id"]))
            $ids[] = $this->input["id"];
        else $ids = $this->input["ids"];
        $data = array('action' => 'country',
            'search' => $search,
            'ids' => $ids);
        $data = http_build_query($data);
        $url = "https://api.siteedit.ru/api/geo/?".$data;
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        $this->result = json_decode(curl_exec($curl), true);
    }
}