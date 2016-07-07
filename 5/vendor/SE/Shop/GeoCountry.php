<?php

namespace SE\Shop;

class GeoCountry extends Base
{

    public function fetch()
    {
        $mysqli = new \mysqli('edgestile.com', 'edgemain_public', '9gRxbs7n', 'edgemain_public');

        if ($mysqli->connect_error) {
            $this->error = 'Не удаётся получить список стран!';
            return;
        }

        $search = ($this->search) ? $this->search : $_GET['search'];
        $ids = array();
        if (empty($this->input["ids"]) && !empty($this->input["id"]))
            $ids[] = $this->input["id"];
        else $ids = $this->input["ids"];
        $idsStr = implode(",", $ids);
        if (empty($idsStr))
            $idsStr = $_GET['id'];

        $sqlQuery = "SELECT * FROM net_country";
        $sqlWhere = array();
        if (!empty($idsStr)) {
            $sqlQuery .= " WHERE id IN (" . $idsStr . ")";
        } else {
            if (!empty($search))
                $sqlWhere[] = "(LOWER(name_ru) LIKE '" . strtolower($search) . "%')";
            if ($idsStr)
                $sqlWhere[] = "(id IN ({$idsStr}))";
            $sqlWhere = implode(" AND ", $sqlWhere);
            if ($sqlWhere)
                $sqlQuery .= " WHERE {$sqlWhere}";
        }
        $sqlQuery .= " ORDER BY name_ru";

        $countriesList = array();
        if ($result = $mysqli->query($sqlQuery)) {
            while ($row = $result->fetch_assoc()) {
                $country['id'] = $row['id'];
                $country['code'] = $row['code'];
                $country['name'] = $row['name_ru'];
                $countriesList[] = $country;
            }
            $result->close();
        }
        $mysqli->close();

        $count = sizeof($countriesList);
        $this->result['count'] = $count;
        $this->result['items'] = $countriesList;
    }
}