<?php

    $ids = array();
    if (empty($json->ids) && !empty($json->id))
        $ids[] = $json->id;
    else $ids = $json->ids;
    $isNew = empty($ids);
    if (!$isNew)
        $idsStr = implode(",", $ids);

    if ($json->code && !$idsStr) {
        $u = new seTable('money_title', 'mt');
        $u->select("id");
        $u->where("name='?'", $json->code);
        $u->fetchOne();
        if ($u->id) {
            $status['status'] = 'error';
            $status['error'] = 'Такая валюта уже существует!';
            outputData($status);
            exit;
        }
    }

    $u = new seTable('money_title', 'mt');

    if ($isNew || !empty($ids)) {
        $isUpdated = false;
        $isUpdated |= setField($isNew, $u, $json->name, 'title');
        $isUpdated |= setField($isNew, $u, $json->code, 'name');
        $isUpdated |= setField($isNew, $u, $json->prefix, 'name_front');
        $isUpdated |= setField($isNew, $u, $json->suffix, 'name_flang');
        $isUpdated |= setField($isNew, $u, $json->cbrCode, 'cbr_kod');
        $isUpdated |= setField($isNew, $u, $json->minSum, 'minsum');

        if ($isUpdated){
            if (!empty($idsStr)) {
                if ($idsStr != "all")
                    $u->where('id in (?)', $idsStr);
                else $u->where('true');
            }
            $idv = $u->save();
            if ($isNew)
                $ids[] = $idv;
        }
    }

    $data['id'] = $ids[0];
    $status = array();
    if (!se_db_error()) {
        $status['status'] = 'ok';
        $status['data'] = $data;
    } else {
        $status['status'] = 'error';
        $status['error'] = se_db_error();
    }

    outputData($status);