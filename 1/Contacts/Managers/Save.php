<?php

    $ids = array();
    if (empty($json->ids) && !empty($json->id))
        $ids[] = $json->id;
    else $ids = $json->ids;
    $isNew = empty($ids);
    if (!$isNew)
        $idsStr = implode(",", $ids);

    if ($ids) {
        $u = new seTable("user_admin", "ua");
        $u->select("id");
        $u->where("id IN (?)", $idsStr);

        $existsId = array();
        $result = $u->getList();
        foreach($result as $item)
            $existsId[] = $item['id'];

        $data = array();
        foreach($ids as $id) {
            if (!in_array($id, $existsId))
                $data[] = array('id_author' => $id, "update" => "N");
        }
        if (!empty($data))
            se_db_InsertList('user_admin', $data);
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