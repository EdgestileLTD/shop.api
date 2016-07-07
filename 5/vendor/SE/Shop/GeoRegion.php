<?php

namespace SE\Shop;

class GeoRegion extends Base
{

    public function fetch()
    {
        $mysqli = new \mysqli('edgestile.com', 'edgemain_public', '9gRxbs7n', 'edgemain_public');

        if ($mysqli->connect_error) {
            $this->error = 'Не удаётся получить список регионов!';
            return;
        }

        $search = $this->search ? $this->search : $_GET['search'];
        $ids = array();
        if (empty($this->input["ids"]) && !empty($this->input["id"]))
            $ids[] = $this->input["id"];
        else $ids = $this->input["ids"];
        $idsStr = implode(",", $ids);
        if (empty($idsStr))
            $idsStr = $_GET['id'];

        $sqlQuery = "SELECT * FROM net_regions";
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

        $regionsList = array();
        if ($result = $mysqli->query($sqlQuery)) {
            while ($row = $result->fetch_assoc()) {
                $region['id'] = $row['id'];
                $region['idCountry'] = $row['id_country'];
                $region['code'] = $row['UTC'];
                $region['name'] = $row['name_ru'];
                $regionsList[] = $region;
            }
            $result->close();
        }
        $mysqli->close();

        $count = sizeof($regionsList);
        $this->result['count'] = $count;
        $this->result['items'] = $regionsList;
        return $regionsList;
    }
}