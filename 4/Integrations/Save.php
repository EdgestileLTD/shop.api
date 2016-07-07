<?php

    function saveParameters($parameters) {

        $idsStr = "";
        foreach ($parameters as $parameter)
            if ($parameter->id) {
                if (!empty($idsStr))
                    $idsStr .= ",";
                $idsStr .= $parameter->id;
            }

        $u = new seTable('shop_integration_parameter','sip');
        if (!empty($idsStr))
            $u->where("NOT id IN (?)", $idsStr)->deletelist();
        else $u->deletelist();

        $data = array();
        foreach ($parameters as $parameter) {
            if ($parameter->id > 0) {
                $isUpdated = false;
                $isUpdated |= setField(0, $u, $parameter->value, 'value');
                if ($isUpdated && $parameter->id) {
                    $u->where('id=?', $parameter->id);
                    $u->save();
                }
            } else $data[] = array("code" => $parameter->code, "value" => $parameter->value);
        }
        if (!empty($data))
            se_db_InsertList('shop_integration_parameter', $data);
    }

    if (isset($json->parameters))
        saveParameters($json->parameters);

    $data['id'] = 1;
    $status = array();
    if (!se_db_error()) {
        $status['status'] = 'ok';
        $status['data'] = $data;
    } else {
        $status['status'] = 'error';
        $status['errortext'] = se_db_error();
    }

    outputData($status);