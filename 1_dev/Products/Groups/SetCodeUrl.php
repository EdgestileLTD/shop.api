<?php
    $ids = array();
    if (empty($json->ids) && !empty($json->id))
        $ids[] = $json->id;
    else $ids = $json->ids;

    if ($ids) {
        $u = new seTable('shop_group','sg');
        $u->select('code_gr');
        $objects = $u->getList();
        $codes = array();
        foreach($objects as $item)
            if (!in_array($item['code_gr'], $codes))
                $codes[] = $item['code_gr'];

        function getCode($name) {
            global $codes;
            $codeName = $name;
            $code = "";
            $i = 1;
            while (!$code || in_array($code, $codes)) {
                $code = strtolower(se_translite_url($codeName));
                $codeName = $name."-$i";
                $i++;
            }
            $codes[] = $code;
            return $code;
        }

        $u->select('id, name');
        $idsStr = implode(",", $ids);
        if ($idsStr != "all")
            $u->where("id in (?)", $idsStr);
        $objects = $u->getList();
        foreach($objects as $item) {
            $u = new seTable('shop_group','sg');
            $code = getCode($item['name']);
            $u->update('code_gr', "'$code'");
            $u->where('id=?', $item['id']);
            $u->save();
        }
    }

    $status = array();
    if (!se_db_error()) {
        $status['status'] = 'ok';
        $status['data'] = $ids;
    } else {
        $status['status'] = 'error';
        $status['error'] = se_db_error();
    }

    outputData($status);