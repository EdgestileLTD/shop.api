<?php

namespace SE\Shop;

class Geo extends Base
{

    public function fetch()
    {
        $mysqli = new mysqli('edgestile.com', 'edgemain_public', '9gRxbs7n', 'edgemain_public');

        if ($mysqli->connect_error) {
            $this->error = 'Не удаётся получить данные!';
            return;
        }

        $idCountry = ($this->input["idCountry"]) ? $this->input["idCountry"] : $_GET['idCountry'];
        $idRegion = ($this->input["idRegion"]) ? $this->input["idRegion"] : $_GET['idRegion'];
        $idCity = ($this->input["idCity"]) ? $this->input["idCity"] : $_GET['idCity'];
        $search = ($this->search) ? $this->search : $_GET['search'];
        if (empty($search) && !$idCountry && !$idRegion && !$idCity) {
            $this->error = 'Не удаётся получить данные! Отсутствует параметр для поиска!';
            exit;
        }

        $sqlQueryCountries = "SELECT id, name_ru, NULL name_p,
                            'country' geo FROM net_country WHERE ";
        if ($idCountry)
            $sqlQueryCountries .= "id = {$idCountry}";
        elseif ($search)
            $sqlQueryCountries .= "(LOWER(name_ru) LIKE '" . strtolower($search) . "%')";
        else $sqlQueryCountries .= "id IS NULL";

        $sqlQueryRegions = "SELECT nr.id, nr.name_ru, nct.name_ru name_p, 'region' geo
                        FROM net_regions nr
                        INNER JOIN net_country nct ON nr.id_country = nct.id WHERE ";
        if ($idRegion)
            $sqlQueryRegions .= "nr.id = {$idRegion}";
        elseif ($search)
            $sqlQueryRegions .= "(LOWER(nr.name_ru) LIKE '" . strtolower($search) . "%')";
        else $sqlQueryRegions .= "nr.id IS NULL";

        $sqlQueryCities = "SELECT nc.id, nc.name_ru, CONCAT_WS(', ', nct.name_ru, nr.name_ru) name_p, 'city' geo
                       FROM net_city nc
                       LEFT JOIN net_regions nr ON nc.region_id = nr.id
                       INNER JOIN net_country nct ON nc.country_id = nct.id WHERE ";
        if ($idCity)
            $sqlQueryCities .= "nc.id = {$idCity}";
        elseif ($search)
            $sqlQueryCities .= "(LOWER(nc.name_ru) LIKE '" . strtolower($search) . "%')";
        else $sqlQueryCities .= "nc.id IS NULL";

        $sql = 'SELECT * FROM (' . $sqlQueryCountries . ' UNION ALL ' . $sqlQueryRegions . ' UNION ALL ' . $sqlQueryCities . ') g ORDER BY g.name_ru';

        $geoList = array();
        if ($result = $mysqli->query($sql)) {
            while ($row = $result->fetch_assoc()) {
                $geoItem['id'] = $row['id'];
                $geoItem['code'] = $row['code'];
                $geoItem['name'] = $row['name_ru'];
                $geoItem['nameParent'] = $row['name_p'];
                $geoItem['geoType'] = $row['geo'];
                $geoList[] = $geoItem;
            }
            $result->close();
        }
        $mysqli->close();

        $count = sizeof($geoList);
        $this->result['count'] = $count;
        $this->result['items'] = $geoList;
    }
}