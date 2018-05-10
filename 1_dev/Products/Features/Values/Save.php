<?php
    function getSortIndex($idFeature)
    {
        $u = new seTable('shop_feature_value_list','sfl');
        $u->select('MAX(sfl.sort) AS sort');
        $u->where('id_feature=?', $idFeature);
        $u->fetchOne();
        return $u->sort + 1;
    }

    $ids = array();
    if (empty($json->ids) && !empty($json->id))
        $ids[] = $json->id;
    else $ids = $json->ids;
    $isNew = empty($ids);
    if (!$isNew)
        $idsStr = implode(",", $ids);

    $u = new seTable('shop_feature_value_list','sfl');

    if ($isNew  || !empty($ids)) {
        $isUpdated = false;
        if ($isNew)
            $json->sortIndex = getSortIndex($json->idFeature);


        $isUpdated |= setField($isNew, $u, $json->name, 'value');
        $isUpdated |= setField($isNew, $u, $json->idFeature, 'id_feature');
        $isUpdated |= setField($isNew, $u, $json->imageFile, 'image');
        $isUpdated |= setField($isNew, $u, $json->color, 'color');
        $isUpdated |= setField($isNew, $u, $json->sortIndex, 'sort');
        if (isset($json->code) && empty($json->code))
            $json->code = strtolower(se_translite_url($json->name));
        $isUpdated |= setField($isNew, $u, $json->code, 'code');

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

        if ($ids && isset($json->items))
            saveFeatures($ids, $json->itens);
    };

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