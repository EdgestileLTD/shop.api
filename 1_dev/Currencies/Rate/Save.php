<?php

    setlocale(LC_NUMERIC, 'C');
    $ids = array();
    if (empty($json->ids) && !empty($json->id))
        $ids[] = $json->id;
    else $ids = $json->ids;
    $idsStr = implode(",", $ids);

    if (empty($json->ids) || empty($json->rateDate) || empty($json->code) || empty($json->rate))
        $errorMessage = 'User data error! Data incorrect!';

    if (!$errorMessage) {
        $u = new seTable('main', 'm');
        $u->select('m.basecurr');
        $base = $u->fetchOne();
        $baseCurrency = $base['basecurr'];

        $u = new seTable('money', 'm');
        $u->where("name = '$json->code' AND date_replace = '$json->rateDate'")->deletelist();

        $u = new seTable('money', 'm');
        $u->money_title_id = $json->id;
        $u->name = $json->code;
        $u->date_replace = $json->rateDate;
        $u->kurs = $json->rate;
        $u->base_currency = $baseCurrency;

        $ids[] = $u->save();

        $errorMessage = mysql_error();
    }

    $data['id'] = $ids[0];
    $status = array();
    if (!$errorMessage) {
        $status['status'] = 'ok';
        $status['data'] = $data;
    } else {
        $status['status'] = 'error';
        $status['errortext'] = $errorMessage;
    }

    outputData($status);