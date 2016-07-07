<?php
    $id = $json->id;
    $idPayment = $json->idPayment;
    $name = $json->name;
    $code = $json->code;
    $value =  $json->value;

    $u = new seTable('bank_accounts', 'ba');
    if ($id) {
       $u->find($id);
    } else {
        if (isset($idPayment))
            $u->id_payment = $idPayment;
        if (isset($name))
            $u->title = $name;
        if (isset($code))
            $u->codename = $code;
    }
    if (isset($value))
        $u->value = $value;
    if ($id) {
        if (!$u->save())
            $id = 0;
    } else
        $id = $u->save();

    $data['id'] = $id;
    $status = array();
    if (!se_db_query()) {
        $status['status'] = 'ok';
        $status['data'] = $data;
    } else {
        $status['status'] = 'error';
        $status['errortext'] = mysql_error();
    }

    outputData($status);