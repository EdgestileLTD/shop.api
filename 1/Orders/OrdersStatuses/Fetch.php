<?php

    $items = array();

    $status['id'] = 'Y';
    $status['name'] = 'Оплачен';
    $status['color'] = '#98FB98';
    $items[] = $status;

    $status['id'] = 'N';
    $status['name'] = 'Не оплачен';
    $status['color'] = '#FFC1C1';
    $items[] = $status;

    $status['id'] = 'K';
    $status['name'] = 'Кредит';
    $status['color'] = '#FFAAAA';
    $items[] = $status;

    $status['id'] = 'P';
    $status['name'] = 'Подарок';
    $status['color'] = null;
    $items[] = $status;

    $status['id'] = 'W';
    $status['name'] = 'В ожидании';
    $status['color'] = null;
    $items[] = $status;

    $status['id'] = 'C';
    $status['name'] = 'Возврат';
    $status['color'] = null;
    $items[] = $status;

    $status['id'] = 'T';
    $status['name'] = 'Тест';
    $status['color'] = null;
    $items[] = $status;


    if (!se_db_error()) {
        $status['status'] = 'ok';
        $status['data'] = array('count'=>sizeof($items), 'items'=>$items);
    } else {
        $status['status'] = 'error';
        $status['errortext'] = se_db_error();
    }
    outputData($status);
