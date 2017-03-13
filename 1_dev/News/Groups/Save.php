<?php

    function getCode($code) {
        $code = strtolower(rus2translit($code));
        if (strlen($code) > 35)
            $code = substr($code, 35);
        $code_n = $code;

        $u = new seTable('news_category','nc');
        $i = 2;
        while ($i < 1000) {
            $u->findlist("nc.ident='$code_n'")->fetchOne();
            if ($u->id)
                $code_n = $code.$i;
            else return $code_n;
            $i++;
        }
        return uniqid();
    }

    $ids = array();
    if (empty($json->ids) && !empty($json->id))
        $ids[] = $json->id;
    else $ids = $json->ids;
    $isNew = empty($ids);
    if (!$isNew)
        $idsStr = implode(",", $ids);

    $u = new seTable('news_category', 'nc');

    if ($isNew || !empty($ids)) {
        $isUpdated = false;
        $isUpdated |= setField($isNew, $u, $json->name, 'title');
        $isUpdated |= setField($isNew, $u, $json->idParent, 'parent_id');
        if ($isNew) {
            if (empty($json->code))
                $json->code = $json->name;
            $json->code = getCode($json->code);
        }
        $isUpdated |= setField($isNew, $u, $json->code, 'ident');

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
    if (!mysql_error()) {
        $status['status'] = 'ok';
        $status['data'] = $data;
    } else {
        $status['status'] = 'error';
        $status['error'] = mysql_error();
    }

    outputData($status);