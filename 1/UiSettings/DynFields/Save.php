<?php

    $types = array("S" => "string", "D" => "number", "T" => "text", "L" => "select", "CB" => "checkbox",
        "R" => "radio", "DT" => "date");

    function getSortIndex()
    {
        $u = new seTable('shop_userfields','su');
        $u->select('MAX(su.sort) AS sort');
        $u->fetchOne();
        return $u->sort + 1;
    }

    function getCode($code) {
        $code_n = $code;
        $u = new seTable('shop_userfields','suf');
        $i = 1;
        while ($i < 1000) {
            $u->findlist("suf.code='$code_n'")->fetchOne();
            if ($u->id)
                $code_n = $code."-$i";
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

    $u = new seTable('shop_userfields','suf');

    if ($isNew || !empty($ids)) {
        $isUpdated = false;
        if ($isNew)
            $json->sortIndex = getSortIndex();
        $isUpdated |= setField($isNew, $u, $json->idGroup, 'id_group');
        $isUpdated |= setField($isNew, $u, $json->isActive, 'enabled');
        $isUpdated |= setField($isNew, $u, $json->name, 'name');
        $isUpdated |= setField($isNew, $u, $json->code, 'code');
        if ($isNew) {
            if (!$u->code)
                $u->code = strtolower(se_translite_url($json->name));
            $u->code = getCode($u->code);
        }
        if (!empty($json->valueType))
            $isUpdated |= setField($isNew, $u, $types[$json->valueType], 'type');
        $isUpdated |= setField($isNew, $u, $json->isRequired, 'required');
        $isUpdated |= setField($isNew, $u, $json->placeholder, 'placeholder');
        $isUpdated |= setField($isNew, $u, $json->mask, 'mask');
        $isUpdated |= setField($isNew, $u, $json->description, 'description');
        $isUpdated |= setField($isNew, $u, $json->values, '`values`');
        $isUpdated |= setField($isNew, $u, $json->sortIndex, 'sort');
        $isUpdated |= setField($isNew, $u, $json->dataTarget, 'data');
        $isUpdated |= setField($isNew, $u, $json->minSize, 'min');
        $isUpdated |= setField($isNew, $u, $json->maxSize, 'max');

        if ($isUpdated) {
            if (!empty($idsStr)) {
                if ($idsStr != "all")
                    $u->where('id in (?)', $idsStr);
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
        $status['errortext'] = se_db_error();
    }

    outputData($status);
