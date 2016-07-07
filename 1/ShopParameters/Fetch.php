<?php

    function getParameters($idGroup) {

        $parameters = array();

        $types = array("string" => "S", "bool" => "B", "select" => "L");

        $u = new seTable("shop_settings", "ss");
        $u->select("ss.*, ssv.id id_value, ssv.value");
        $u->leftjoin("shop_setting_values ssv", "ss.id=ssv.id_setting");
        $u->where("ss.id_group=?", $idGroup);
        $u->groupby("ss.id");
        $u->orderby("sort");
        $objects = $u->getList();

        foreach ($objects as $row) {
            $parameter = null;
            $parameter['id'] = $row["id"];
            $parameter['idValue'] = $row["id_value"];
            $parameter['code'] = $row["code"];
            $parameter['name'] = $row["name"];
            $parameter['valueType'] = $types[$row["type"]];
            $parameter['value'] = $row["value"];
            if ($parameter['valueType'] == "L") {
                $list = explode(',', $row["list_values"]);
                $listValues = array();
                foreach ($list as $value) {
                    $arr = explode('|', $value);
                    $listValues[$arr[0]] = $arr[1];
                    if ($parameter['listValues'])
                        $parameter['listValues'] .= ",";
                    $parameter['listValues'] .= $arr[1];
                }
                if (empty($parameter['value']) && $listValues)
                    $parameter['value'] = $listValues[$row["default"]];
                else $parameter['value'] = $listValues[$row["value"]];

            } else {
                if (empty($parameter['value']))
                    $parameter['value'] = $row["default"];
                $parameter['listValues'] = $row["list_values"];
            }
            $parameter['note'] = $row["description"];
            $parameter['isActive'] = (bool) $row["enabled"];
            $parameters[] = $parameter;
        }

        return $parameters;
    }

    $u = new seTable("shop_setting_groups", "ssg");
    $u->select("id, name, description");
    $u->orderby("sort");
    $objects = $u->getList();

    foreach ($objects as $row) {
        $group = null;
        $group['id'] = $row['id'];
        $group['name'] = $row['name'];
        $group['note'] = $row['description'];
        $group['parameters'] = getParameters($row['id']);
        $items[] = $group;
    }

    $data['count'] = sizeof($items);
    $data['items'] = $items;

    $status = array();
    if (!se_db_error()) {
        $status['status'] = 'ok';
        $status['data'] = $data;
    } else {
        $status['status'] = 'error';
        $status['errortext'] = se_db_error();
    }

    outputData($status);