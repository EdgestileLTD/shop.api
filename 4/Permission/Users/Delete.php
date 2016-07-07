<?php

    if ($json->ids) {
        $idsStr = implode(",", $json->ids);
        $u = se_db_query("UPDATE se_user SET is_manager = 0 WHERE id IN ({$idsStr})");
    }

    $status = array();
    if (!se_db_error()) {
        $status['status'] = 'ok';
    } else {
        $status['status'] = 'error';
        $status['errortext'] = 'Не удаётся исключить контакты из пользователей!';
    }

    outputData($status);