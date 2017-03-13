<?php
    $u = new seTable('se_group');
    $isNew = (empty($json->id));
    $isUpdated = false;
    $id = intval($json->id);
    $json->id_parent = ($json->id_parent) ? $json->id_parent : 'null';
    $isUpdated |= setField($isNew, $u, $json->id_parent, 'id_parent', 'INT(10) UNSIGNED default NULL', 1);
    $isUpdated |= setField($isNew, $u, $json->name, 'title');
    $isUpdated |= setField($isNew, $u, $json->name, 'name');
    if ($isUpdated){
        if ($json->id) {
            $u->where('id=?', $json->id);
        }
        if ($id_save = $u->save(true)){
         $id = ($isNew) ? $id_save : $json->id;
        }
    } 

    $state = array();

    if ($id > 0)
        $data['id'] = $id;

    if (!se_db_error()) {
        $status['status'] = 'ok';
        $status['data'] = $data;
    } else {
        $status['status'] = 'error';
        $status['error'] = se_db_error();
    }
    outputData($status);


