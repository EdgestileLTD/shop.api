<?php

    function saveParameters($parameters) {

        $idsStr = array();
        foreach ($parameters as $parameter) {
            if ($parameter->isActive && $parameter->id) {
                if (!empty($idsEnabled))
                    $idsEnabled .= ",";
                $idsEnabled .= $parameter->id;
            }
            $idsStr[] = $parameter->id;
        }
        $idsStr = implode(",", $idsStr);
        $u = new seTable("shop_settings", "ss");
        $u->select("ss.*");
        $u->where("ss.id IN (?)", $idsStr);
        $objects = $u->getList();
        $settings = array();
        foreach ($objects as $row) {
            $values = array();
            if ($row["type"] == "select") {
                $list = explode(',', $row["list_values"]);
                foreach ($list as $value) {
                    $arr = explode('|', $value);
                    $values[$arr[1]] = $arr[0];
                }
                $settings[$row["id"]] = $values;
            }
        }

        foreach ($parameters as $parameter) {
            if (array_key_exists($parameter->id, $settings) && array_key_exists($parameter->value, $settings[$parameter->id]))
                $parameter->value = $settings[$parameter->id][$parameter->value];
            if ($parameter->idValue) {
                $u = new seTable("shop_setting_values", "ssv");
                $u->select("value");
                $u->where("id=?", $parameter->idValue);
                $u->fetchOne();

                if ($u->value != $parameter->value) {
                    $u->addupdate("value", "'$parameter->value'");
                    $u->where("id=?", $parameter->idValue);
                    $u->save();
                }
            } else {
                $data[] = array('id_setting' => $parameter->id, 'value' => $parameter->value);
            }
        }
        if ($data)
            se_db_InsertList("shop_setting_values", $data);

        se_db_query("UPDATE shop_settings SET enabled = 0");
        if ($idsEnabled) {
            $u = new seTable("shop_settings", "ss");
            $u->addupdate("enabled", 1);
            $u->where("id IN (?)", $idsEnabled);
            $u->save();
        }
    }

    if (isset($json->parameters) && !empty($json->parameters))
        saveParameters($json->parameters);

    $data['id'] = 1;
    $status = array();
    if (!se_db_error()) {
        $status['status'] = 'ok';
        $status['data'] = $data;
    } else {
        $status['status'] = 'error';
        $status['error'] = se_db_error();
    }

    outputData($status);