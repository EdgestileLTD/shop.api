<?php

$profile = trim($json->profile);
if (!empty($profile)) {
    $isNew = true;
    $u = new seTable('import_profile');
    $u->select("id");
    $u->where("name = '?'", $profile);
    $result = $u->fetchOne();
    if (!empty($result)) {
        $json->id = $result["id"];
        $isNew = false;
    }

    $settings = json_encode($_SESSION["import"][$json->target]);

    $u = new seTable('import_profile');
    setField($isNew, $u, $json->profile, 'name');
    setField($isNew, $u, $json->target, 'target');
    setField($isNew, $u, $settings, 'settings');
    if (!$isNew)
        $u->where("id = ?", $json->id);
    $u->save();
}


$status = [];

if (!se_db_error()) {
    $status['status'] = 'ok';
} else {
    $status['status'] = 'error';
    $status['error'] = 'Не удаётся сохранить категорию для товаров!';
}

outputData($status);