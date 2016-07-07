<?php

    $ids = array();
    if (empty($json->ids) && !empty($json->id))
        $ids[] = $json->id;
    else $ids = $json->ids;
    $isNew = empty($ids);
    if (!$isNew)
        $idsStr = implode(",", $ids);

    if ($ids) {
        if (!empty($json->idsRoles)) {

            $idsRoles = implode(",", $json->idsRoles);

            $u = new seTable("permission_role_user", "pru");
            $u->where("id_user IN (?)", $idsStr);
            $u->andWhere("NOT id_role IN (?)", $idsRoles);
            $u->deleteList();

            foreach ($ids as $id)
                foreach ($json->idsRoles as $idRole) {
                    $sql = "INSERT IGNORE INTO permission_role_user (id_user, id_role) VALUE ({$id}, {$idRole})";
                    se_db_query($sql);
                }

        } else
            $u = se_db_query("UPDATE se_user SET is_manager = 1 WHERE id IN ({$idsStr})");
    }

    $data['id'] = $ids[0];
    $status = array();
    if (!se_db_error()) {
        $status['status'] = 'ok';
        $status['data'] = $data;
    } else {
        $status['status'] = 'error';
        $status['errortext'] = 'Не удаётся сохранить данные о пользователях!';
    }

    outputData($status);