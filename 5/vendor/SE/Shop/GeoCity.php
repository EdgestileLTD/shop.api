<?php

namespace SE\Shop;

class GeoCity extends Base
{

    public function fetch()
    {
        $mysqli = new \mysqli('edgestile.com', 'edgemain_public', '9gRxbs7n', 'edgemain_public');

        if ($mysqli->connect_error) {
            $this->error = 'Не удаётся получить список городов!';
            return;
        }

        $idCountry = $this->input["idCountry"] ? $this->input["idCountry"] : $_GET['idCountry'];
        $idRegion = $this->input["idRegion"] ? $this->input["idRegion"] : $_GET['idRegion'];
        $search = ($this->input["searchText"]) ? $this->input["searchText"] : $_GET['search'];
        $ids = array();
        if (empty($this->input["ids"]) && !empty($this->input["id"]))
            $ids[] = $this->input["id"];
        else $ids = $this->input["ids"];
        $idsStr = implode(",", $ids);
        if (empty($idsStr))
            $idsStr = $_GET['id'];

        $sqlQuery = "SELECT * FROM net_city";
        $sqlWhere = array();
        if (!empty($idsStr)) {
            $sqlQuery .= " WHERE id IN (" . $idsStr . ")";
        } else {
            if (!empty($search))
                $sqlWhere[] = "(LOWER(name_ru) LIKE '" . strtolower($search) . "%')";
            if ($idCountry)
                $sqlWhere[] = "(country_id = {$idCountry})";
            if ($idRegion)
                $sqlWhere[] = "(region_id = {$idRegion})";
            $sqlWhere = implode(" AND ", $sqlWhere);
            if ($sqlWhere)
                $sqlQuery .= " WHERE {$sqlWhere}";
        }
        $sqlQuery .= " ORDER BY name_ru";

        $citiesList = array();
        if ($result = $mysqli->query($sqlQuery)) {
            while ($row = $result->fetch_assoc()) {
                $city['id'] = $row['id'];
                $city['idCountry'] = $row['country_id'];
                $city['idRegion'] = $row['region_id'];
                $city['name'] = $row['name_ru'];
                $citiesList[] = $city;
            }
            $result->close();
        }
        $mysqli->close();

        $count = sizeof($citiesList);
        $this->result['count'] = $count;
        $this->result['items'] = $citiesList;
    }
}