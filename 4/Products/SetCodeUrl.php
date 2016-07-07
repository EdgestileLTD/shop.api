<?php
    $ids = array();
    if (empty($json->ids) && !empty($json->id))
        $ids[] = $json->id;
    else $ids = $json->ids;

    if ($ids) {
        $u = new seTable('shop_price','sp');
        $u->select('id, LOWER(code) code');
        $objects = $u->getList();
        $codes = array();
        foreach($objects as $item)
            $codes[$item['id']] = $item['code'];

        function getCode($id, $name) {
            global $codes;
            $codeName = $name;
            $code = "";
            $i = 1;
            while (!$code || in_array($code, $codes)) {
                $code = strtolower(se_translite_url($codeName));
                $codeName = $name."-$i";
                if ($codes[$id] == $code)
                    break;
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
            $u = new seTable('shop_price','sp');
            $code = getCode($item['id'], $item['name']);
            $u->update('code', "'$code'");
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
        $status['errortext'] = se_db_error();
    }

    outputData($status);